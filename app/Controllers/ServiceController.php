<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\HttpClient;
use App\Core\Security;
use App\Providers\LizzySubProvider;
use App\Providers\PaypointProvider;
use App\Providers\VtpassProvider;
use App\Services\ServiceCatalog;
use App\Services\TransactionService;
use App\Services\ProviderAuthService;

final class ServiceController
{
    public function __construct(private App $app)
    {
    }

    public function show(string $type): void
    {
        Security::requireLogin();
        if (!(new ServiceCatalog($this->app->db()))->isEnabled($type)) {
            $this->app->view('services/unavailable', ['title' => 'Service unavailable', 'type' => $type]);
            return;
        }
        $purchaseToken = bin2hex(random_bytes(24));
        $_SESSION['_purchase_tokens'][$type] = $purchaseToken;
        $this->app->view('services/show', ['title' => ucfirst($type), 'type' => $type, 'purchaseToken' => $purchaseToken]);
    }

    public function purchase(string $type): void
    {
        $user = Security::requireLogin();
        Security::verifyCsrf();
        $purchaseToken = (string) ($_POST['_purchase_token'] ?? '');
        $expectedToken = (string) ($_SESSION['_purchase_tokens'][$type] ?? '');
        unset($_SESSION['_purchase_tokens'][$type]);
        if ($purchaseToken === '' || $expectedToken === '' || !hash_equals($expectedToken, $purchaseToken)) {
            $_SESSION['notice'] = 'This transaction request has already been submitted or expired. Check History before trying again.';
            redirect_to('/history');
            return;
        }
        if (!(new ServiceCatalog($this->app->db()))->isEnabled($type)) {
            $_SESSION['notice'] = 'This service is currently unavailable.';
            redirect_to('/service/' . urlencode($type));
            return;
        }
        try {
            if (in_array($type, ['airtime', 'data'], true) && !empty($_POST['bulk_enabled'])) {
                $summary = $this->processInlineBulk((int) $user['id'], $type, $_POST);
                $_SESSION['notice'] = 'Bulk ' . str_replace('_', ' ', $type) . ' processed. Success: ' . $summary['success'] . ', failed: ' . $summary['failed'] . '.';
                $this->refreshSessionUser((int) $user['id']);
                redirect_to('/service/' . urlencode($type));
                return;
            }
            $result = (new TransactionService($this->app->db()))->purchase((int) $user['id'], $type, $_POST);
            $this->refreshSessionUser((int) $user['id']);
            redirect_to('/receipt/' . urlencode($result['reference']));
        } catch (\Throwable $e) {
            $_SESSION['notice'] = $e->getMessage();
            redirect_to('/service/' . urlencode($type));
            return;
        }
    }

    public function receipt(string $reference): void
    {
        $user = Security::requireLogin();
        $stmt = $this->app->db()->prepare('SELECT * FROM transactions WHERE reference = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$reference, $user['id']]);
        $transaction = $stmt->fetch();
        if (!$transaction) {
            http_response_code(404);
            exit('Receipt not found');
        }
        $this->app->view('services/receipt', ['title' => 'Receipt', 'transaction' => $transaction, 'user' => $user]);
    }

    public function verify(string $type): void
    {
        Security::requireLogin();
        header('Content-Type: application/json');

        $provider = strtolower(trim((string) ($_GET['provider'] ?? '')));
        $recipient = trim((string) ($_GET['recipient'] ?? ''));
        $planId = trim((string) ($_GET['plan_id'] ?? ''));

        if ($recipient === '' || $provider === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Provider and customer number are required.']);
            return;
        }

        try {
            echo json_encode($this->verifyCustomer($type, $provider, $recipient, $planId));
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    private function processInlineBulk(int $userId, string $type, array $input): array
    {
        $raw = (string) ($input['bulk_recipients'] ?? '');
        $recipients = array_values(array_filter(array_map(
            static fn (string $value): string => preg_replace('/\D/', '', trim($value)) ?? '',
            preg_split('/,/', $raw) ?: []
        )));

        if (empty($recipients)) {
            throw new \InvalidArgumentException('Enter at least one phone number for bulk purchase.');
        }

        $summary = ['success' => 0, 'failed' => 0];
        $service = new TransactionService($this->app->db());
        foreach ($recipients as $recipient) {
            $payload = $input;
            $payload['recipient'] = $recipient;
            unset($payload['bulk_enabled'], $payload['bulk_recipients']);
            try {
                $result = $service->purchase($userId, $type, $payload);
                $summary[$result['status'] === 'success' ? 'success' : 'failed']++;
            } catch (\Throwable) {
                $summary['failed']++;
            }
        }

        return $summary;
    }

    private function refreshSessionUser(int $userId): void
    {
        $stmt = $this->app->db()->prepare('SELECT id, first_name, last_name, username, email, phone, role, package_id, status, balance, referral_code FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $_SESSION['user'] = $stmt->fetch() ?: ($_SESSION['user'] ?? []);
    }

    private function verifyCustomer(string $service, string $provider, string $recipient, string $planId): array
    {
        $stmt = $this->app->db()->prepare("SELECT * FROM provider_configs WHERE service = ? AND status = 'active' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$service]);
        $config = $stmt->fetch();

        if ($config && !empty($config['base_url'])) {
            if (in_array((string) ($config['provider_key'] ?? ''), ['vtpass_cable', 'vtpass_electricity'], true)) {
                return (new VtpassProvider($this->app->db(), $config))->verifyCustomer($service, $provider, $recipient, $planId);
            }
            if ((string) ($config['provider_key'] ?? '') === 'lizzysub_electricity') {
                return (new LizzySubProvider($this->app->db(), $config))->verifyCustomer($service, $provider, $recipient, $planId);
            }
            if (in_array((string) ($config['provider_key'] ?? ''), ['paypoint_cable', 'paypoint_electricity'], true)) {
                return (new PaypointProvider($this->app->db(), $config))->verifyCustomer($service, $provider, $recipient, $planId);
            }
            $credentials = json_decode((string) ($config['credentials'] ?? '{}'), true) ?: [];
            $headers = ProviderAuthService::headers($credentials, 'bearer');
            $response = (new HttpClient())->postJson(rtrim((string) $config['base_url'], '/') . '/verify', [
                'service' => $service,
                'provider' => $provider,
                'recipient' => $recipient,
                'plan_id' => $planId,
            ], $headers);
            if ($response['ok'] && is_array($response['body'])) {
                $body = $response['body'];
                return [
                    'ok' => (($body['status'] ?? 'success') !== 'failed'),
                    'name' => (string) ($body['name'] ?? $body['customer_name'] ?? $body['customerName'] ?? ''),
                    'address' => (string) ($body['address'] ?? ''),
                    'message' => (string) ($body['message'] ?? 'Verification successful.'),
                    'raw' => $body,
                ];
            }
            throw new \RuntimeException('Unable to verify customer from provider API.');
        }

        $label = match ($service) {
            'cable' => 'Cable subscriber',
            'electricity' => 'Meter customer',
            default => 'Customer',
        };
        return [
            'ok' => true,
            'name' => $label . ' ' . substr(preg_replace('/\D/', '', $recipient), -4),
            'address' => '',
            'message' => 'Demo verification successful.',
            'raw' => ['provider' => $provider, 'recipient' => $recipient],
        ];
    }
}
