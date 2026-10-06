<?php

declare(strict_types=1);

namespace App\Services;

use App\Providers\ProviderFactory;
use PDO;

final class TransactionService
{
    public function __construct(private PDO $db)
    {
    }

    public function purchase(int $userId, string $service, array $input): array
    {
        $reference = 'BVT' . date('YmdHis') . random_int(1000, 9999);
        $originalAmount = (float) ($input['amount'] ?? 0);
        if (!(new ServiceCatalog($this->db))->isEnabled($service)) {
            $this->recordFailedTransaction($userId, $service, $input, $originalAmount, $reference, 'Unsupported service.');
            throw new \InvalidArgumentException('Unsupported service.');
        }

        if ($originalAmount <= 0) {
            $this->recordFailedTransaction($userId, $service, $input, $originalAmount, $reference, 'Invalid amount.');
            throw new \InvalidArgumentException('Invalid amount.');
        }

        try {
            $this->assertTransactionVelocity($userId);
        } catch (\Throwable $e) {
            $this->recordFailedTransaction($userId, $service, $input, $originalAmount, $reference, $e->getMessage());
            throw $e;
        }

        $provider = ProviderFactory::makeForPayload($this->db, $service, $input);
        $this->ensurePlanPackagePriceTable();

        $this->db->beginTransaction();
        try {
            $user = $this->lockUser($userId);
            $this->verifyPin($user, (string) ($input['pin'] ?? ''));
            $originalAmount = $this->resolvePlanAmount($user, $service, $input, $originalAmount);
            $pricing = (new CommerceService($this->db))->pricingFor($user, $service, $originalAmount, trim((string) ($input['coupon_code'] ?? '')) ?: null);
            $amount = (float) $pricing['payable'];

            if ((float) $user['balance'] < $amount) {
                throw new \RuntimeException('Insufficient wallet balance.');
            }

            $before = (float) $user['balance'];
            $after = $before - $amount;
            $this->db->prepare('UPDATE users SET balance = ? WHERE id = ?')->execute([$after, $userId]);
            $this->ledger($userId, 'debit', $amount, $before, $after, $reference, 'Service purchase: ' . $service);

            $api = $provider->purchase($service, $input, $reference);
            $status = ($api['status'] ?? 'failed') === 'success' ? 'success' : 'failed';

            if ($status === 'failed') {
                $this->db->prepare('UPDATE users SET balance = ? WHERE id = ?')->execute([$before, $userId]);
                $this->ledger($userId, 'credit', $amount, $after, $before, $reference, 'Automatic reversal: ' . $service);
                $after = $before;
                $this->assertFailedPurchaseIsNeutral($userId, $reference, $before, $service);
            }

            $stmt = $this->db->prepare(
                'INSERT INTO transactions (user_id, service, recipient, amount, status, reference, response_message, details, balance_before, balance_after, receipt_options) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $userId,
                $service,
                (string) ($input['recipient'] ?? $input['meter_number'] ?? ''),
                $amount,
                $status,
                $reference,
                (string) ($api['message'] ?? ''),
                json_encode($this->adminTransactionDetails($service, $input, $reference, $api, $status, 'provider_response')),
                $before,
                $after,
                json_encode(array_merge($this->receiptOptions($service, $input), [
                    'original_amount' => $originalAmount,
                    'discount' => $pricing['discount'],
                    'discount_rules' => $pricing['applied'],
                ])),
            ]);

            $transactionId = (int) $this->db->lastInsertId();
            $this->db->commit();
            $this->runPostPurchaseSideEffects($user, $service, $originalAmount, $amount, $reference, $status, $transactionId);
            return ['reference' => $reference, 'status' => $status];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                try {
                    $this->db->rollBack();
                } catch (\Throwable) {
                    // Preserve the original purchase error instead of replacing it with a rollback message.
                }
            }
            $this->recordFailedTransaction($userId, $service, $input, $originalAmount, $reference, $e->getMessage());
            throw $e;
        }
    }

    public function queueBulk(int $userId, array $input): string
    {
        $batchRef = 'BULK' . date('YmdHis') . random_int(1000, 9999);
        $rows = preg_split('/\r\n|\r|\n/', trim((string) ($input['rows'] ?? ''))) ?: [];
        $service = (string) ($input['service'] ?? '');

        if (!(new ServiceCatalog($this->db))->isEnabled($service)) {
            throw new \InvalidArgumentException('Unsupported bulk service.');
        }

        $stmt = $this->db->prepare('INSERT INTO bulk_transactions (batch_reference, user_id, service, payload, status) VALUES (?, ?, ?, ?, ?)');
        foreach ($rows as $row) {
            if (trim($row) === '') {
                continue;
            }
            $stmt->execute([$batchRef, $userId, $service, json_encode($this->parseBulkRow($row)), 'queued']);
        }

        (new AuditService($this->db))->record($userId, 'bulk.queued', 'bulk', null, ['batch_reference' => $batchRef, 'service' => $service]);
        return $batchRef;
    }

    public function processBulkBatch(int $userId, string $batchReference, string $pin, int $limit = 100): array
    {
        $stmt = $this->db->prepare('SELECT * FROM bulk_transactions WHERE batch_reference = ? AND user_id = ? AND status = ? ORDER BY id ASC LIMIT ' . max(1, $limit));
        $stmt->execute([$batchReference, $userId, 'queued']);
        $rows = $stmt->fetchAll();

        $summary = ['success' => 0, 'failed' => 0];
        foreach ($rows as $row) {
            $payload = json_decode((string) $row['payload'], true) ?: [];
            $payload['pin'] = $pin;

            $this->db->prepare('UPDATE bulk_transactions SET status = ? WHERE id = ?')->execute(['processing', $row['id']]);

            try {
                $result = $this->purchase($userId, (string) $row['service'], $payload);
                $txn = $this->transactionByReference($result['reference']);
                $this->db->prepare('UPDATE bulk_transactions SET status = ?, transaction_id = ?, processed_at = NOW() WHERE id = ?')
                    ->execute([$result['status'] === 'success' ? 'success' : 'failed', $txn['id'] ?? null, $row['id']]);
                $summary[$result['status'] === 'success' ? 'success' : 'failed']++;
            } catch (\Throwable $e) {
                $this->db->prepare('UPDATE bulk_transactions SET status = ?, error_message = ?, processed_at = NOW() WHERE id = ?')
                    ->execute(['failed', $e->getMessage(), $row['id']]);
                $summary['failed']++;
            }
        }

        return $summary;
    }

    private function lockUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ? FOR UPDATE');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) {
            throw new \RuntimeException('User not found.');
        }
        (new SecurityPolicyService($this->db))->assertNotBlocked($user);
        return $user;
    }

    private function verifyPin(array $user, string $pin): void
    {
        if (!password_verify($pin, (string) $user['transaction_pin_hash'])) {
            $settings = (new SettingsService($this->db))->many();
            if (($settings['security_lockout_enabled'] ?? 'on') === 'on') {
                (new SecurityPolicyService($this->db))->hitAndMaybeBlock(
                    (int) $user['id'],
                    'pin',
                    'Too many wrong transaction PIN attempts',
                    (int) ($settings['wrong_pin_limit'] ?? 3),
                    (int) ($settings['wrong_pin_block_minutes'] ?? 15)
                );
            }
            throw new \RuntimeException('Invalid transaction PIN.');
        }
        (new SecurityPolicyService($this->db))->clearAttempts((int) $user['id'], 'pin');
    }

    private function assertTransactionVelocity(int $userId): void
    {
        $settings = (new SettingsService($this->db))->many();
        $limit = max(1, (int) ($settings['transaction_per_minute_limit'] ?? 5));
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM transactions WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)');
        $stmt->execute([$userId]);
        if ((int) $stmt->fetchColumn() >= $limit) {
            (new SecurityPolicyService($this->db))->blockUser($userId, 'More than ' . $limit . ' transactions attempted in one minute', 1440);
            throw new \RuntimeException('Account blocked due to too many transactions in one minute. Please contact admin.');
        }
    }

    private function notifyTransaction(array $user, string $service, float $amount, string $reference, string $status): void
    {
        $html = '<p>Transaction ' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '.</p><p>Service: <b>' . htmlspecialchars($service, ENT_QUOTES, 'UTF-8') . '</b><br>Amount: ₦' . number_format($amount, 2) . '<br>Reference: ' . htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') . '</p>';
        $notify = new NotificationService($this->db);
        $notify->admins('Transaction notification: ' . $reference, $html, 'admin_notify_transactions');
        $notify->user($user, 'Your transaction update: ' . $reference, $html, 'user_notify_transactions');
    }

    private function runPostPurchaseSideEffects(array $user, string $service, float $originalAmount, float $amount, string $reference, string $status, int $transactionId): void
    {
        try {
            (new AuditService($this->db))->record((int) $user['id'], 'transaction.' . $status, 'transaction', $transactionId, [
                'service' => $service,
                'reference' => $reference,
            ]);
            if ($status === 'success') {
                (new CommerceService($this->db))->rewardAfterSuccess($user, $service, $originalAmount, $reference);
            }
            $this->notifyTransaction($user, $service, $amount, $reference, $status);
        } catch (\Throwable) {
            // Purchases and reversals are already committed; notification/reward issues must not alter wallet outcome.
        }
    }

    private function ledger(int $userId, string $direction, float $amount, float $before, float $after, string $reference, string $description): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO wallet_ledger (user_id, direction, amount, balance_before, balance_after, reference, description) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $direction, $amount, $before, $after, $reference, $description]);
    }

    private function parseBulkRow(string $row): array
    {
        $parts = array_map('trim', str_getcsv($row));
        return [
            'raw' => $row,
            'recipient' => $parts[0] ?? '',
            'provider' => $parts[1] ?? '',
            'plan_id' => $parts[2] ?? '',
            'amount' => isset($parts[3]) ? (float) $parts[3] : 0,
            'quantity' => isset($parts[4]) ? max(1, (int) $parts[4]) : 1,
        ];
    }

    private function transactionByReference(string $reference): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM transactions WHERE reference = ? LIMIT 1');
        $stmt->execute([$reference]);
        return $stmt->fetch() ?: null;
    }

    private function receiptOptions(string $service, array $input): array
    {
        if ($service !== 'electricity') {
            return [];
        }

        return [
            'show_amount' => !empty($input['receipt_show_amount']),
            'display_amount' => $input['receipt_display_amount'] ?? null,
            'customer_address' => trim((string) ($input['customer_address'] ?? '')),
            'extra_details' => trim((string) ($input['receipt_extra_details'] ?? '')),
        ];
    }

    private function resolvePlanAmount(array $user, string $service, array $input, float $fallback): float
    {
        $planId = trim((string) ($input['plan_id'] ?? ''));
        $provider = strtolower(trim((string) ($input['provider'] ?? '')));
        if ($planId === '' || in_array($service, ['airtime', 'electricity'], true)) {
            return $fallback;
        }

        $stmt = $this->db->prepare(
            "SELECT COALESCE(pp.selling_price, p.selling_price) AS payable_price
             FROM service_plans p
             LEFT JOIN service_plan_package_prices pp
               ON pp.service_plan_id = p.id AND pp.package_id = ?
             WHERE p.service = ? AND p.plan_id = ? AND p.status = 'active'
               AND (? = '' OR p.provider = ?)
             ORDER BY p.id DESC
             LIMIT 1"
        );
        $stmt->execute([(int) ($user['package_id'] ?? 0), $service, $planId, $provider, $provider]);
        $price = $stmt->fetchColumn();
        if ($price === false) {
            return $fallback;
        }
        return (float) $price;
    }

    private function ensurePlanPackagePriceTable(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS service_plan_package_prices (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            service_plan_id BIGINT UNSIGNED NOT NULL,
            package_id BIGINT UNSIGNED NOT NULL,
            selling_price DECIMAL(20,2) NOT NULL DEFAULT 0.00,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY service_plan_package_unique (service_plan_id, package_id),
            KEY service_plan_package_plan_idx (service_plan_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private function recordFailedTransaction(int $userId, string $service, array $input, float $amount, string $reference, string $message): void
    {
        $exists = $this->db->prepare('SELECT COUNT(*) FROM transactions WHERE reference = ?');
        $exists->execute([$reference]);
        if ((int) $exists->fetchColumn() > 0) {
            return;
        }

        $balance = 0.0;
        $stmt = $this->db->prepare('SELECT balance FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $found = $stmt->fetchColumn();
        if ($found !== false) {
            $balance = (float) $found;
        }

        $insert = $this->db->prepare(
            'INSERT INTO transactions (user_id, service, recipient, amount, status, reference, response_message, details, balance_before, balance_after, receipt_options) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $userId,
            $service,
            (string) ($input['recipient'] ?? $input['meter_number'] ?? ''),
            max(0, $amount),
            'failed',
            $reference,
            $message,
            json_encode([
                'admin_debug' => [
                    'failure_stage' => 'pre_purchase',
                    'failure_reason' => $message,
                    'request_payload' => $this->safePayload($input),
                    'reference' => $reference,
                ],
                'provider' => $input['provider'] ?? null,
                'service_type' => $input['service_type'] ?? null,
                'plan_id' => $input['plan_id'] ?? null,
                'quantity' => $input['quantity'] ?? null,
                'failure_stage' => 'pre_purchase',
            ]),
            $balance,
            $balance,
            json_encode($this->receiptOptions($service, $input)),
        ]);

        (new AuditService($this->db))->record($userId, 'transaction.failed', 'transaction', (int) $this->db->lastInsertId(), [
            'service' => $service,
            'reference' => $reference,
            'message' => $message,
        ]);
    }

    private function assertFailedPurchaseIsNeutral(int $userId, string $reference, float $expectedBalance, string $service): void
    {
        $stmt = $this->db->prepare('SELECT balance FROM users WHERE id = ? FOR UPDATE');
        $stmt->execute([$userId]);
        $current = (float) $stmt->fetchColumn();
        if (abs($current - $expectedBalance) > 0.009) {
            $this->db->prepare('UPDATE users SET balance = ? WHERE id = ?')->execute([$expectedBalance, $userId]);
            $direction = $current < $expectedBalance ? 'credit' : 'debit';
            $this->ledger($userId, $direction, abs($expectedBalance - $current), $current, $expectedBalance, $reference, 'Failed transaction balance correction: ' . $service);
        }

        $ledger = $this->db->prepare(
            "SELECT COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END), 0)
             FROM wallet_ledger
             WHERE user_id = ? AND reference = ?"
        );
        $ledger->execute([$userId, $reference]);
        $net = (float) $ledger->fetchColumn();
        if (abs($net) > 0.009) {
            $direction = $net > 0 ? 'credit' : 'debit';
            $this->ledger($userId, $direction, abs($net), $expectedBalance, $expectedBalance, $reference, 'Failed transaction ledger neutralizer: ' . $service);
        }
    }

    private function adminTransactionDetails(string $service, array $input, string $reference, array $api, string $status, string $stage): array
    {
        $details = is_array($api['details'] ?? null) ? $api['details'] : [];
        $message = (string) ($api['message'] ?? '');

        return array_merge($details, [
            'admin_debug' => [
                'status' => $status,
                'service' => $service,
                'reference' => $reference,
                'failure_stage' => $status === 'failed' ? $stage : null,
                'failure_reason' => $status === 'failed' ? ($message !== '' ? $message : 'Provider returned a failed status.') : null,
                'request_payload' => $this->safePayload($input),
                'provider_message' => $message,
                'provider_details' => $details,
                'recorded_at' => date('c'),
            ],
        ]);
    }

    private function safePayload(array $payload): array
    {
        $blocked = ['_csrf', 'csrf', 'pin', 'password', 'password_confirmation', 'secret_key', 'api_key', 'token'];
        $safe = [];
        foreach ($payload as $key => $value) {
            $name = strtolower((string) $key);
            if (in_array($name, $blocked, true) || str_contains($name, 'password') || str_contains($name, 'secret') || str_contains($name, 'token')) {
                $safe[$key] = '[hidden]';
                continue;
            }
            $safe[$key] = is_scalar($value) || $value === null ? $value : '[complex value]';
        }
        return $safe;
    }
}
