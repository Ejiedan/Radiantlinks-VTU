<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class WalletService
{
    public function __construct(private PDO $db)
    {
        $this->ensureFundingSchema();
    }

    public function credit(int $userId, float $amount, string $reference, string $description): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero.');
        }

        $this->db->beginTransaction();
        try {
            $user = $this->lockUser($userId);
            $before = (float) $user['balance'];
            $after = $before + $amount;
            $this->db->prepare('UPDATE users SET balance = ? WHERE id = ?')->execute([$after, $userId]);
            $this->ledger($userId, 'credit', $amount, $before, $after, $reference, $description);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function debit(int $userId, float $amount, string $reference, string $description): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero.');
        }

        $this->db->beginTransaction();
        try {
            $user = $this->lockUser($userId);
            $before = (float) $user['balance'];
            if ($before < $amount) {
                throw new \RuntimeException('Cannot deduct more than the customer wallet balance.');
            }
            $after = $before - $amount;
            $this->db->prepare('UPDATE users SET balance = ? WHERE id = ?')->execute([$after, $userId]);
            $this->ledger($userId, 'debit', $amount, $before, $after, $reference, $description);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function resetBalance(int $userId, float $targetAmount, string $reference, string $description): void
    {
        if ($targetAmount < 0) {
            throw new \InvalidArgumentException('Wallet balance cannot be negative.');
        }

        $this->db->beginTransaction();
        try {
            $user = $this->lockUser($userId);
            $before = (float) $user['balance'];
            $after = round($targetAmount, 2);
            $difference = round(abs($after - $before), 2);
            $this->db->prepare('UPDATE users SET balance = ? WHERE id = ?')->execute([$after, $userId]);
            if ($difference > 0) {
                $this->ledger($userId, $after > $before ? 'credit' : 'debit', $difference, $before, $after, $reference, $description);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function fundingSettings(): array
    {
        $settings = (new SettingsService($this->db))->many();
        return [
            'manual_enabled' => ($settings['funding_manual_enabled'] ?? 'on') === 'on',
            'gateway_enabled' => ($settings['funding_gateway_enabled'] ?? 'off') === 'on',
            'dedicated_enabled' => ($settings['funding_dedicated_enabled'] ?? 'on') === 'on',
            'gateway_provider' => (string) ($settings['funding_gateway_provider'] ?? 'demo'),
            'manual_bank_details' => (string) ($settings['manual_bank_details'] ?? "Bank: Your Bank\nAccount Name: Radiantlinks VTU\nAccount Number: 0000000000"),
            'charges' => [
                'manual_bank' => $this->chargeSummary('manual_bank', $settings),
                'gateway' => $this->chargeSummary('gateway', $settings),
                'dedicated_account' => $this->chargeSummary('dedicated_account', $settings),
            ],
        ];
    }

    public function chargeFor(string $method, float $amount): array
    {
        $settings = (new SettingsService($this->db))->many();
        $prefix = match ($method) {
            'gateway' => 'gateway',
            'dedicated_account' => 'dedicated',
            default => 'manual',
        };
        $type = (string) ($settings['funding_' . $prefix . '_charge_type'] ?? 'fixed');
        $value = (float) ($settings['funding_' . $prefix . '_charge_value'] ?? 0);
        $charge = $type === 'percentage' ? ($amount * $value / 100) : $value;
        $charge = max(0, round($charge, 2));
        return [
            'type' => $type === 'percentage' ? 'percentage' : 'fixed',
            'value' => $value,
            'charge' => min($amount, $charge),
            'net' => max(0, $amount - min($amount, $charge)),
        ];
    }

    public function dedicatedAccountFor(int $userId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM dedicated_accounts WHERE user_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    public function generateDedicatedAccount(int $userId): array
    {
        $settings = $this->fundingSettings();
        if (!$settings['dedicated_enabled']) {
            throw new \RuntimeException('Dedicated account generation is currently disabled.');
        }

        $existing = $this->dedicatedAccountFor($userId);
        if ($existing) {
            return $existing;
        }

        $provider = $settings['gateway_provider'] ?: 'demo';
        $user = $this->lockUser($userId);
        if (in_array($provider, ['securewave', 'paymentpoint'], true)) {
            $kyc = (new KycService($this->db))->approvedForUser($userId);
            $generated = (new PaymentGatewayService($this->db))->generateDedicatedAccount($provider, $user, $kyc);
            $accountNumber = $generated['account_number'];
            $accountName = $generated['account_name'];
            $bankName = $generated['bank_name'];
            $reference = $generated['reference'];
            $rawResponse = $generated['raw_response'];
        } else {
            $accountNumber = $this->demoAccountNumber($userId);
            $accountName = trim(($user['first_name'] ?? 'Customer') . ' ' . ($user['last_name'] ?? ''));
            $accountName = $accountName !== '' ? $accountName . ' - Radiantlinks VTU' : 'Radiantlinks VTU Customer';
            $bankName = $provider === 'demo' ? 'Radiantlinks Demo Bank' : ucfirst($provider) . ' Virtual Bank';
            $reference = 'DVA' . date('YmdHis') . random_int(100, 999);
            $rawResponse = ['mode' => 'local_demo', 'message' => 'Replace demo provider with real gateway API credentials in production.'];
        }

        $stmt = $this->db->prepare(
            'INSERT INTO dedicated_accounts (user_id, provider, account_name, account_number, bank_name, reference, status, raw_response) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $provider,
            $accountName,
            $accountNumber,
            $bankName,
            $reference,
            'active',
            json_encode($rawResponse),
        ]);

        return $this->dedicatedAccountFor($userId) ?: [];
    }

    public function initializeGatewayFunding(int $userId, float $amount): array
    {
        $settings = $this->fundingSettings();
        if (!$settings['gateway_enabled']) {
            throw new \RuntimeException('Gateway funding is currently disabled.');
        }
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero.');
        }

        $user = $this->lockUser($userId);
        $reference = 'PAY' . date('YmdHis') . random_int(100, 999);
        $checkout = (new PaymentGatewayService($this->db))->initializeCheckout($settings['gateway_provider'], $user, $amount, $reference);
        $charge = $this->chargeFor('gateway', $amount);
        $stmt = $this->db->prepare(
            'INSERT INTO wallet_funding_requests (user_id, amount, charge_amount, credited_amount, method, payment_reference, proof_note, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $amount,
            $charge['charge'],
            $charge['net'],
            'gateway',
            $reference,
            json_encode(['provider' => $settings['gateway_provider'], 'checkout' => $checkout['raw_response']]),
            'pending',
        ]);

        return $checkout;
    }

    public function requestFunding(int $userId, float $amount, string $method, string $paymentReference, string $proofNote): int
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero.');
        }
        $settings = $this->fundingSettings();
        if ($method === 'manual_bank' && !$settings['manual_enabled']) {
            throw new \RuntimeException('Manual funding is currently disabled.');
        }
        if ($method === 'gateway' && !$settings['gateway_enabled']) {
            throw new \RuntimeException('Gateway funding is currently disabled.');
        }
        if ($method === 'dedicated_account' && !$settings['dedicated_enabled']) {
            throw new \RuntimeException('Dedicated account funding is currently disabled.');
        }
        $charge = $this->chargeFor($method, $amount);

        $stmt = $this->db->prepare(
            'INSERT INTO wallet_funding_requests (user_id, amount, charge_amount, credited_amount, method, payment_reference, proof_note, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $amount, $charge['charge'], $charge['net'], $method, $paymentReference, $proofNote, 'pending']);
        return (int) $this->db->lastInsertId();
    }

    public function approveFunding(int $requestId, int $adminId, string $adminNote = ''): void
    {
        $this->db->beginTransaction();
        try {
            $request = $this->lockFundingRequest($requestId);
            if ($request['status'] !== 'pending') {
                throw new \RuntimeException('Funding request has already been reviewed.');
            }

            $user = $this->lockUser((int) $request['user_id']);
            $before = (float) $user['balance'];
            $gross = (float) $request['amount'];
            $amount = (float) ($request['credited_amount'] ?? 0);
            if ($amount <= 0) {
                $charge = $this->chargeFor((string) $request['method'], $gross);
                $amount = (float) $charge['net'];
                $this->db->prepare('UPDATE wallet_funding_requests SET charge_amount = ?, credited_amount = ? WHERE id = ?')
                    ->execute([$charge['charge'], $charge['net'], $requestId]);
            }
            $after = $before + $amount;
            $reference = 'FUND' . str_pad((string) $requestId, 8, '0', STR_PAD_LEFT);

            $this->db->prepare('UPDATE users SET balance = ? WHERE id = ?')->execute([$after, $request['user_id']]);
            $this->ledger((int) $request['user_id'], 'credit', $amount, $before, $after, $reference, 'Wallet funding approved via ' . $request['method']);

            $this->db->prepare('UPDATE wallet_funding_requests SET status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?')
                ->execute(['approved', $adminNote, $adminId, $requestId]);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function autoCreditFromGateway(string $provider, string $accountNumber, float $amount, string $reference, array $payload = []): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Gateway amount must be greater than zero.');
        }
        if (trim($accountNumber) === '' || trim($reference) === '') {
            throw new \InvalidArgumentException('Gateway account number and reference are required.');
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT * FROM dedicated_accounts WHERE account_number = ? AND status = 'active' LIMIT 1 FOR UPDATE");
            $stmt->execute([$accountNumber]);
            $account = $stmt->fetch();
            if (!$account) {
                throw new \RuntimeException('Dedicated account not found.');
            }

            $exists = $this->db->prepare('SELECT COUNT(*) FROM wallet_ledger WHERE reference = ?');
            $exists->execute([$reference]);
            if ((int) $exists->fetchColumn() > 0) {
                $this->db->commit();
                return;
            }

            $charge = $this->chargeFor('dedicated_account', $amount);
            if ((float) $charge['net'] <= 0) {
                throw new \RuntimeException('Funding charge cannot consume the entire payment amount.');
            }
            $user = $this->lockUser((int) $account['user_id']);
            $before = (float) $user['balance'];
            $after = $before + (float) $charge['net'];

            $funding = $this->db->prepare(
                'INSERT INTO wallet_funding_requests (user_id, amount, charge_amount, credited_amount, method, payment_reference, proof_note, status, reviewed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $funding->execute([
                (int) $account['user_id'],
                $amount,
                $charge['charge'],
                $charge['net'],
                'dedicated_account',
                $reference,
                json_encode(['provider' => $provider, 'account_number' => $accountNumber, 'payload' => $payload]),
                'approved',
            ]);
            $this->db->prepare('UPDATE users SET balance = ? WHERE id = ?')->execute([$after, $account['user_id']]);
            $this->ledger((int) $account['user_id'], 'credit', (float) $charge['net'], $before, $after, $reference, 'Automatic dedicated account funding');
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function rejectFunding(int $requestId, int $adminId, string $adminNote = ''): void
    {
        $stmt = $this->db->prepare("UPDATE wallet_funding_requests SET status = 'rejected', admin_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND status = 'pending'");
        $stmt->execute([$adminNote, $adminId, $requestId]);
        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException('Funding request could not be rejected.');
        }
    }

    private function lockUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) {
            throw new \RuntimeException('User not found.');
        }
        return $user;
    }

    private function lockFundingRequest(int $requestId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM wallet_funding_requests WHERE id = ? FOR UPDATE');
        $stmt->execute([$requestId]);
        $request = $stmt->fetch();
        if (!$request) {
            throw new \RuntimeException('Funding request not found.');
        }
        return $request;
    }

    private function ledger(int $userId, string $direction, float $amount, float $before, float $after, string $reference, string $description): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO wallet_ledger (user_id, direction, amount, balance_before, balance_after, reference, description) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $direction, $amount, $before, $after, $reference, $description]);
    }

    private function chargeSummary(string $method, array $settings): string
    {
        $prefix = match ($method) {
            'gateway' => 'gateway',
            'dedicated_account' => 'dedicated',
            default => 'manual',
        };
        $type = (string) ($settings['funding_' . $prefix . '_charge_type'] ?? 'fixed');
        $value = (float) ($settings['funding_' . $prefix . '_charge_value'] ?? 0);
        if ($value <= 0) {
            return 'No charge';
        }
        return $type === 'percentage' ? rtrim(rtrim(number_format($value, 2), '0'), '.') . '%' : '₦' . number_format($value, 2);
    }

    private function demoAccountNumber(int $userId): string
    {
        return '9' . str_pad((string) $userId, 9, '0', STR_PAD_LEFT);
    }

    private function ensureFundingSchema(): void
    {
        $this->addColumn('wallet_funding_requests', 'charge_amount', 'DECIMAL(20,2) NOT NULL DEFAULT 0.00 AFTER amount');
        $this->addColumn('wallet_funding_requests', 'credited_amount', 'DECIMAL(20,2) NOT NULL DEFAULT 0.00 AFTER charge_amount');
        $this->db->exec("CREATE TABLE IF NOT EXISTS dedicated_accounts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            provider VARCHAR(80) NOT NULL,
            account_name VARCHAR(191) NOT NULL,
            account_number VARCHAR(80) NOT NULL,
            bank_name VARCHAR(120) NOT NULL,
            reference VARCHAR(120) NULL,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            raw_response JSON NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY dedicated_account_number_unique (account_number),
            KEY dedicated_account_user_idx (user_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private function addColumn(string $table, string $column, string $definition): void
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $stmt->execute([$table, $column]);
        if ((int) $stmt->fetchColumn() === 0) {
            $this->db->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
        }
    }
}
