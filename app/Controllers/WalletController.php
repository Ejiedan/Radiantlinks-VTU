<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Security;
use App\Services\WalletService;

final class WalletController
{
    public function __construct(private App $app)
    {
    }

    public function index(): void
    {
        $user = Security::requireLogin();
        $stmt = $this->app->db()->prepare('SELECT * FROM wallet_ledger WHERE user_id = ? ORDER BY id DESC LIMIT 100');
        $stmt->execute([$user['id']]);
        $funding = $this->app->db()->prepare('SELECT * FROM wallet_funding_requests WHERE user_id = ? ORDER BY id DESC LIMIT 20');
        $funding->execute([$user['id']]);
        $wallet = new WalletService($this->app->db());
        $this->app->view('wallet/index', [
            'title' => 'Wallet',
            'user' => $this->freshUser((int) $user['id']),
            'ledger' => $stmt->fetchAll(),
            'fundingRequests' => $funding->fetchAll(),
            'fundingSettings' => $wallet->fundingSettings(),
            'dedicatedAccount' => $wallet->dedicatedAccountFor((int) $user['id']),
            'notice' => $_SESSION['notice'] ?? null,
        ]);
        unset($_SESSION['notice']);
    }

    public function generateAccount(): void
    {
        $user = Security::requireLogin();
        Security::verifyCsrf();
        try {
            (new WalletService($this->app->db()))->generateDedicatedAccount((int) $user['id']);
            $_SESSION['notice'] = 'Dedicated account generated successfully.';
        } catch (\Throwable $e) {
            $_SESSION['notice'] = $e->getMessage();
        }
        redirect_to('/wallet');
    }

    public function requestFunding(): void
    {
        $user = Security::requireLogin();
        Security::verifyCsrf();

        try {
            $wallet = new WalletService($this->app->db());
            $method = (string) ($_POST['method'] ?? 'manual_bank');
            if ($method === 'gateway') {
                $checkout = $wallet->initializeGatewayFunding((int) $user['id'], (float) $_POST['amount']);
                redirect_to($checkout['url']);
                return;
            }

            $wallet->requestFunding(
                (int) $user['id'],
                (float) $_POST['amount'],
                $method,
                trim((string) ($_POST['payment_reference'] ?? '')),
                trim((string) ($_POST['proof_note'] ?? ''))
            );
            $_SESSION['notice'] = 'Funding request submitted for admin review.';
        } catch (\Throwable $e) {
            $_SESSION['notice'] = $e->getMessage();
        }

        redirect_to('/wallet');
    }

    public function gatewayWebhook(string $routeProvider = ''): void
    {
        $raw = file_get_contents('php://input') ?: '';
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }

        $settings = (new \App\Services\SettingsService($this->app->db()))->many();
        $provider = strtolower(trim($routeProvider ?: (string) ($payload['provider'] ?? $payload['gateway'] ?? $settings['funding_gateway_provider'] ?? 'gateway')));
        $reference = $this->payloadValue($payload, ['reference', 'payment_reference', 'transaction_reference', 'transactionReference', 'data.reference', 'data.transaction_reference', 'transaction.reference']);
        $deliveryId = $this->logWebhook($provider, $reference, $payload);
        $secrets = $this->webhookSecrets($provider, $settings);
        header('Content-Type: application/json; charset=UTF-8');
        if (!$secrets) {
            $this->finishWebhookLog($deliveryId, 'rejected', false, 503, 'Payment webhook secret is not configured.');
            http_response_code(503);
            echo json_encode(['ok' => false, 'message' => 'Payment webhook is not configured']);
            return;
        }
        $signature = trim((string) (
            $_SERVER['HTTP_X_RADIANTLINKS_SIGNATURE']
            ?? $_SERVER['HTTP_X_SIGNATURE']
            ?? $_SERVER['HTTP_X_PAYLOAD_SIGNATURE']
            ?? $_SERVER['HTTP_X_WEBHOOK_SIGNATURE']
            ?? $_SERVER['HTTP_X_PAYMENTPOINT_SIGNATURE']
            ?? ''
        ));
        $signature = preg_replace('/^sha256=/i', '', $signature) ?? '';
        $signatureValid = false;
        foreach ($secrets as $secret) {
            $hex = hash_hmac('sha256', $raw, $secret);
            $base64 = base64_encode(hash_hmac('sha256', $raw, $secret, true));
            if ($signature !== '' && (hash_equals($hex, $signature) || hash_equals($base64, $signature))) {
                $signatureValid = true;
                break;
            }
        }
        if (!$signatureValid) {
            $this->finishWebhookLog($deliveryId, 'rejected', false, 401, 'Invalid webhook signature.');
            http_response_code(401);
            echo json_encode(['ok' => false, 'message' => 'Invalid signature']);
            return;
        }

        $status = strtolower($this->payloadValue($payload, ['status', 'event', 'data.status', 'transaction.status', 'data.transaction.status']));
        if (!in_array($status, ['success', 'successful', 'paid', 'payment_successful', 'completed', 'credit'], true)) {
            $this->finishWebhookLog($deliveryId, 'ignored', true, 200, 'Ignored non-success event.');
            echo json_encode(['ok' => true, 'message' => 'Ignored non-success event']);
            return;
        }

        try {
            (new WalletService($this->app->db()))->autoCreditFromGateway(
                $provider,
                $this->payloadValue($payload, ['account_number', 'accountNumber', 'account', 'virtual_account', 'data.account_number', 'data.accountNumber', 'data.account.account_number', 'transaction.account_number']),
                (float) $this->payloadValue($payload, ['amount', 'paid_amount', 'amountPaid', 'settled_amount', 'data.amount', 'data.paid_amount', 'transaction.amount']),
                $reference ?: ('PAY' . date('YmdHis') . random_int(100, 999)),
                $payload
            );
            $this->finishWebhookLog($deliveryId, 'processed', true, 200, 'Wallet funding processed.');
            echo json_encode(['ok' => true]);
        } catch (\Throwable $e) {
            $this->finishWebhookLog($deliveryId, 'failed', true, 400, $e->getMessage());
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function gatewayCallback(): void
    {
        $provider = trim((string) ($_REQUEST['provider'] ?? 'payment gateway'));
        $reference = trim((string) ($_REQUEST['reference'] ?? $_REQUEST['trxref'] ?? $_REQUEST['transaction_reference'] ?? ''));
        $status = strtolower(trim((string) ($_REQUEST['status'] ?? '')));
        $successful = in_array($status, ['success', 'successful', 'paid', 'completed'], true);
        $_SESSION['notice'] = $successful
            ? 'Payment return received' . ($reference !== '' ? ' for ' . $reference : '') . '. Your wallet will update after secure ' . $provider . ' webhook confirmation.'
            : 'Payment return received. No wallet credit was made. Check your funding history or try again.';
        redirect_to(!empty($_SESSION['user']) ? '/wallet' : '/login');
    }

    public function adminCredit(): void
    {
        $admin = Security::requireAdmin();
        Security::verifyCsrf();

        try {
            (new WalletService($this->app->db()))->credit(
                (int) $_POST['user_id'],
                (float) $_POST['amount'],
                'ADMIN' . date('YmdHis') . random_int(100, 999),
                'Admin wallet credit by #' . $admin['id']
            );
            $_SESSION['notice'] = 'Wallet credited successfully.';
        } catch (\Throwable $e) {
            $_SESSION['notice'] = $e->getMessage();
        }

        redirect_to('/admin/users');
    }

    public function adminDebit(): void
    {
        $admin = Security::requireAdmin();
        Security::verifyCsrf();

        try {
            (new WalletService($this->app->db()))->debit(
                (int) $_POST['user_id'],
                (float) $_POST['amount'],
                'DEBIT' . date('YmdHis') . random_int(100, 999),
                'Admin wallet deduction by #' . $admin['id']
            );
            $_SESSION['notice'] = 'Wallet deducted successfully.';
        } catch (\Throwable $e) {
            $_SESSION['notice'] = $e->getMessage();
        }

        redirect_to('/admin/users');
    }

    public function adminReset(): void
    {
        $admin = Security::requireAdmin();
        Security::verifyCsrf();

        try {
            (new WalletService($this->app->db()))->resetBalance(
                (int) $_POST['user_id'],
                (float) $_POST['amount'],
                'RESET' . date('YmdHis') . random_int(100, 999),
                'Admin wallet reset by #' . $admin['id']
            );
            $_SESSION['notice'] = 'Wallet balance reset successfully.';
        } catch (\Throwable $e) {
            $_SESSION['notice'] = $e->getMessage();
        }

        redirect_to('/admin/users');
    }

    public function adminFunding(): void
    {
        Security::requireAdmin();
        $sql = 'SELECT fr.*, u.email, u.first_name, u.last_name FROM wallet_funding_requests fr INNER JOIN users u ON u.id = fr.user_id ORDER BY fr.id DESC LIMIT 300';
        $requests = $this->app->db()->query($sql)->fetchAll();
        $this->app->view('admin/funding', [
            'title' => 'Funding Requests',
            'requests' => $requests,
            'notice' => $_SESSION['notice'] ?? null,
        ]);
        unset($_SESSION['notice']);
    }

    public function approveFunding(string $id): void
    {
        $admin = Security::requireAdmin();
        Security::verifyCsrf();

        try {
            (new WalletService($this->app->db()))->approveFunding((int) $id, (int) $admin['id'], trim((string) ($_POST['admin_note'] ?? '')));
            $_SESSION['notice'] = 'Funding request approved and wallet credited.';
        } catch (\Throwable $e) {
            $_SESSION['notice'] = $e->getMessage();
        }

        redirect_to('/admin/funding');
    }

    public function rejectFunding(string $id): void
    {
        $admin = Security::requireAdmin();
        Security::verifyCsrf();

        try {
            (new WalletService($this->app->db()))->rejectFunding((int) $id, (int) $admin['id'], trim((string) ($_POST['admin_note'] ?? '')));
            $_SESSION['notice'] = 'Funding request rejected.';
        } catch (\Throwable $e) {
            $_SESSION['notice'] = $e->getMessage();
        }

        redirect_to('/admin/funding');
    }

    private function webhookSecrets(string $provider, array $settings): array
    {
        $secrets = [trim((string) ($settings['payment_webhook_secret'] ?? ''))];
        $stmt = $this->app->db()->prepare("SELECT credentials FROM provider_configs WHERE service = 'wallet_gateway' AND provider_key = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$provider]);
        $credentials = json_decode((string) ($stmt->fetchColumn() ?: '{}'), true) ?: [];
        foreach (['webhook_secret', 'secret_key', 'token'] as $field) {
            $secrets[] = trim((string) ($credentials[$field] ?? ''));
        }
        return array_values(array_unique(array_filter($secrets, static fn (string $secret): bool => $secret !== '')));
    }

    private function logWebhook(string $provider, string $reference, array $payload): int
    {
        try {
            $this->ensureWebhookTable();
            $stmt = $this->app->db()->prepare('INSERT INTO payment_webhook_logs (provider, reference, payload, status) VALUES (?, ?, ?, ?)');
            $stmt->execute([$provider, $reference !== '' ? $reference : null, json_encode($payload), 'received']);
            return (int) $this->app->db()->lastInsertId();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function finishWebhookLog(int $id, string $status, bool $signatureValid, int $httpStatus, string $message): void
    {
        if ($id <= 0) {
            return;
        }
        try {
            $this->app->db()->prepare('UPDATE payment_webhook_logs SET status = ?, signature_valid = ?, http_status = ?, response_message = ?, processed_at = NOW() WHERE id = ?')
                ->execute([$status, $signatureValid ? 1 : 0, $httpStatus, substr($message, 0, 500), $id]);
        } catch (\Throwable) {
            // Webhook processing must not fail merely because audit logging failed.
        }
    }

    private function ensureWebhookTable(): void
    {
        $this->app->db()->exec("CREATE TABLE IF NOT EXISTS payment_webhook_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            provider VARCHAR(80) NOT NULL,
            reference VARCHAR(191) NULL,
            payload LONGTEXT NOT NULL,
            signature_valid TINYINT(1) NOT NULL DEFAULT 0,
            http_status SMALLINT UNSIGNED NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'received',
            response_message VARCHAR(500) NULL,
            received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            processed_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY payment_webhook_provider_idx (provider, received_at),
            KEY payment_webhook_reference_idx (reference)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private function freshUser(int $id): array
    {
        $stmt = $this->app->db()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: [];
    }

    private function payloadValue(array $payload, array $paths): string
    {
        foreach ($paths as $path) {
            $cursor = $payload;
            foreach (explode('.', $path) as $part) {
                if (is_array($cursor) && array_key_exists($part, $cursor)) {
                    $cursor = $cursor[$part];
                    continue;
                }
                if (is_array($cursor) && ctype_digit($part) && array_key_exists((int) $part, $cursor)) {
                    $cursor = $cursor[(int) $part];
                    continue;
                }
                $cursor = null;
                break;
            }
            if ($cursor !== null && $cursor !== '') {
                return is_scalar($cursor) ? (string) $cursor : json_encode($cursor);
            }
        }
        return '';
    }
}
