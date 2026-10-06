<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class CommerceService
{
    public function __construct(private PDO $db)
    {
    }

    public function ensureTables(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS user_packages (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(120) NOT NULL,
            description TEXT NULL,
            upgrade_cost DECIMAL(20,2) NOT NULL DEFAULT 0.00,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->exec("CREATE TABLE IF NOT EXISTS commerce_rules (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(150) NOT NULL,
            rule_type ENUM('coupon','package_discount','cashback','referral') NOT NULL,
            service VARCHAR(80) NULL,
            package_id BIGINT UNSIGNED NULL,
            coupon_code VARCHAR(80) NULL,
            value_type ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
            value DECIMAL(20,2) NOT NULL DEFAULT 0.00,
            max_discount DECIMAL(20,2) NULL,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            starts_at DATETIME NULL,
            ends_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY commerce_rule_lookup_idx (rule_type, service, status, coupon_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->addColumn('users', 'package_id', 'BIGINT UNSIGNED NULL AFTER role');
        $this->addColumn('users', 'referral_code', 'VARCHAR(40) NULL AFTER api_key');
        $this->addColumn('users', 'referred_by', 'BIGINT UNSIGNED NULL AFTER referral_code');
    }

    public function packages(): array
    {
        $this->ensureTables();
        return $this->db->query('SELECT * FROM user_packages ORDER BY name')->fetchAll();
    }

    public function rules(): array
    {
        $this->ensureTables();
        return $this->db->query('SELECT r.*, p.name AS package_name FROM commerce_rules r LEFT JOIN user_packages p ON p.id = r.package_id ORDER BY r.id DESC')->fetchAll();
    }

    public function savePackage(array $input): void
    {
        $this->ensureTables();
        $this->addColumn('user_packages', 'upgrade_cost', 'DECIMAL(20,2) NOT NULL DEFAULT 0.00 AFTER description');
        $stmt = $this->db->prepare('INSERT INTO user_packages (name, description, upgrade_cost, status) VALUES (?, ?, ?, ?)');
        $stmt->execute([
            trim((string) ($input['name'] ?? '')),
            trim((string) ($input['description'] ?? '')),
            (float) ($input['upgrade_cost'] ?? 0),
            (string) ($input['status'] ?? 'active'),
        ]);
    }

    public function saveRule(array $input): void
    {
        $this->ensureTables();
        $stmt = $this->db->prepare('INSERT INTO commerce_rules (name, rule_type, service, package_id, coupon_code, value_type, value, max_discount, status, starts_at, ends_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            trim((string) ($input['name'] ?? '')),
            (string) ($input['rule_type'] ?? 'coupon'),
            trim((string) ($input['service'] ?? '')) ?: null,
            !empty($input['package_id']) ? (int) $input['package_id'] : null,
            strtoupper(trim((string) ($input['coupon_code'] ?? ''))) ?: null,
            (string) ($input['value_type'] ?? 'percentage'),
            (float) ($input['value'] ?? 0),
            $input['max_discount'] !== '' ? (float) ($input['max_discount'] ?? 0) : null,
            (string) ($input['status'] ?? 'active'),
            trim((string) ($input['starts_at'] ?? '')) ?: null,
            trim((string) ($input['ends_at'] ?? '')) ?: null,
        ]);
    }

    public function pricingFor(array $user, string $service, float $amount, ?string $couponCode): array
    {
        if (!$this->db->inTransaction()) {
            $this->ensureTables();
        }
        $discount = 0.0;
        $applied = [];

        foreach ($this->activeRules('package_discount', $service) as $rule) {
            if (!empty($rule['package_id']) && (int) $rule['package_id'] !== (int) ($user['package_id'] ?? 0)) {
                continue;
            }
            $value = $this->ruleValue($rule, $amount);
            $discount += $value;
            $applied[] = $rule['name'];
        }

        if ($couponCode) {
            foreach ($this->activeRules('coupon', $service, strtoupper($couponCode)) as $rule) {
                $value = $this->ruleValue($rule, $amount);
                $discount += $value;
                $applied[] = $rule['name'];
            }
        }

        $discount = min($amount, round($discount, 2));
        return ['payable' => max(0, $amount - $discount), 'discount' => $discount, 'applied' => $applied];
    }

    public function rewardAfterSuccess(array $user, string $service, float $amount, string $reference): void
    {
        foreach ($this->activeRules('cashback', $service) as $rule) {
            $cashback = $this->ruleValue($rule, $amount);
            if ($cashback > 0) {
                (new WalletService($this->db))->credit((int) $user['id'], $cashback, 'CB' . $reference, 'Cashback reward');
            }
        }

        if (!empty($user['referred_by'])) {
            $limit = max(1, (int) (new SettingsService($this->db))->get('referral_bonus_times', 1));
            $count = $this->referralRewardCount((int) $user['referred_by'], (int) $user['id']);
            if ($count >= $limit) {
                return;
            }
            foreach ($this->activeRules('referral', $service) as $rule) {
                $bonus = $this->ruleValue($rule, $amount);
                if ($bonus > 0) {
                    (new WalletService($this->db))->credit((int) $user['referred_by'], $bonus, 'RF' . $reference, 'Referral reward from user ' . (int) $user['id']);
                }
            }
        }
    }

    public function changePackage(int $userId, int $packageId): void
    {
        $this->ensureTables();
        $stmt = $this->db->prepare("SELECT * FROM user_packages WHERE id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$packageId]);
        $package = $stmt->fetch();
        if (!$package) {
            throw new \RuntimeException('Package not available.');
        }

        $cost = (float) $package['upgrade_cost'];
        $this->db->beginTransaction();
        try {
            $userStmt = $this->db->prepare('SELECT * FROM users WHERE id = ? FOR UPDATE');
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch();
            if (!$user) {
                throw new \RuntimeException('User not found.');
            }
            if ($cost > 0) {
                if ((float) $user['balance'] < $cost) {
                    throw new \RuntimeException('Insufficient wallet balance for package change.');
                }
                $before = (float) $user['balance'];
                $after = $before - $cost;
                $reference = 'PKG' . date('YmdHis') . random_int(1000, 9999);
                $this->db->prepare('UPDATE users SET balance = ?, package_id = ? WHERE id = ?')->execute([$after, $packageId, $userId]);
                $this->db->prepare('INSERT INTO wallet_ledger (user_id, direction, amount, balance_before, balance_after, reference, description) VALUES (?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$userId, 'debit', $cost, $before, $after, $reference, 'Package change to ' . $package['name']]);
                $this->db->prepare('INSERT INTO transactions (user_id, service, recipient, amount, status, reference, response_message, details, balance_before, balance_after, receipt_options) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([
                        $userId,
                        'package_change',
                        (string) $package['name'],
                        $cost,
                        'success',
                        $reference,
                        'Package changed successfully.',
                        json_encode([
                            'package_id' => $packageId,
                            'package_name' => $package['name'],
                            'type' => 'package_change',
                        ]),
                        $before,
                        $after,
                        json_encode(['show_amount' => true]),
                    ]);
            } else {
                $this->db->prepare('UPDATE users SET package_id = ? WHERE id = ?')->execute([$packageId, $userId]);
            }
            $this->db->commit();
            (new AuditService($this->db))->record($userId, 'package.changed', 'package', $packageId, [
                'package' => $package['name'],
                'cost' => $cost,
            ]);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function referralsFor(int $userId): array
    {
        $this->ensureTables();
        $stmt = $this->db->prepare('SELECT id, first_name, last_name, email, created_at FROM users WHERE referred_by = ? ORDER BY id DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function allReferrals(): array
    {
        $this->ensureTables();
        return $this->db->query('SELECT u.id, u.first_name, u.last_name, u.email, u.created_at, r.email AS referrer_email FROM users u LEFT JOIN users r ON r.id = u.referred_by WHERE u.referred_by IS NOT NULL ORDER BY u.id DESC')->fetchAll();
    }

    public function currentPackage(?int $packageId): ?array
    {
        if (!$packageId) {
            return null;
        }
        $this->ensureTables();
        $stmt = $this->db->prepare('SELECT * FROM user_packages WHERE id = ? LIMIT 1');
        $stmt->execute([$packageId]);
        return $stmt->fetch() ?: null;
    }

    private function activeRules(string $type, string $service, ?string $coupon = null): array
    {
        $sql = "SELECT * FROM commerce_rules WHERE rule_type = ? AND status = 'active' AND (service IS NULL OR service = '' OR service = ?) AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW())";
        $params = [$type, $service];
        if ($coupon !== null) {
            $sql .= ' AND coupon_code = ?';
            $params[] = $coupon;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function ruleValue(array $rule, float $amount): float
    {
        $value = (float) $rule['value'];
        $computed = $rule['value_type'] === 'fixed' ? $value : ($amount * $value / 100);
        if ($rule['max_discount'] !== null) {
            $computed = min($computed, (float) $rule['max_discount']);
        }
        return round(max(0, $computed), 2);
    }

    private function referralRewardCount(int $referrerId, int $referredId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM wallet_ledger WHERE user_id = ? AND direction = 'credit' AND reference LIKE 'RF%' AND description = ?");
        $stmt->execute([$referrerId, 'Referral reward from user ' . $referredId]);
        return (int) $stmt->fetchColumn();
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
