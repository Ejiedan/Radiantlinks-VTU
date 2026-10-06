<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class SecurityPolicyService
{
    public function __construct(private PDO $db)
    {
    }

    public function ensureColumns(): void
    {
        $this->addColumn('users', 'blocked_until', 'DATETIME NULL AFTER status');
        $this->addColumn('users', 'blocked_reason', 'VARCHAR(255) NULL AFTER blocked_until');
    }

    public function blockUser(int $userId, string $reason, int $minutes): void
    {
        $this->ensureColumns();
        $stmt = $this->db->prepare("UPDATE users SET status = 'blocked', blocked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE), blocked_reason = ? WHERE id = ?");
        $stmt->execute([max(1, $minutes), $reason, $userId]);

        $user = $this->user($userId);
        (new NotificationService($this->db))->admins(
            'User blocked: ' . ($user['email'] ?? '#' . $userId),
            '<p>User <b>' . htmlspecialchars((string) ($user['email'] ?? $userId), ENT_QUOTES, 'UTF-8') . '</b> was blocked.</p><p>Reason: ' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '</p>',
            'admin_notify_blocks'
        );
    }

    public function unblockIfExpired(array $user): array
    {
        if (!$this->db->inTransaction()) {
            $this->ensureColumns();
        }
        if (($user['status'] ?? '') !== 'blocked' || empty($user['blocked_until'])) {
            return $user;
        }

        if (strtotime((string) $user['blocked_until']) > time()) {
            return $user;
        }

        $this->db->prepare("UPDATE users SET status = 'active', blocked_until = NULL, blocked_reason = NULL WHERE id = ?")->execute([$user['id']]);
        $user['status'] = 'active';
        $user['blocked_until'] = null;
        $user['blocked_reason'] = null;
        return $user;
    }

    public function assertNotBlocked(array $user): void
    {
        $user = $this->unblockIfExpired($user);
        if (($user['status'] ?? '') === 'blocked') {
            $until = !empty($user['blocked_until']) ? ' until ' . $user['blocked_until'] : '';
            throw new \RuntimeException('Account blocked' . $until . '. Please contact admin.');
        }
        if (($user['status'] ?? '') !== 'active') {
            throw new \RuntimeException('Account is not active. Please contact admin.');
        }
    }

    public function hitAndMaybeBlock(int $userId, string $type, string $reason, int $maxAttempts, int $blockMinutes): void
    {
        $bucket = $type . ':user:' . $userId;
        $throttle = new LoginThrottleService($this->db);
        $throttle->hit($bucket, $blockMinutes);
        if ($throttle->tooMany($bucket, max(1, $maxAttempts))) {
            $throttle->clear($bucket);
            $this->blockUser($userId, $reason, $blockMinutes);
        }
    }

    public function clearAttempts(int $userId, string $type): void
    {
        (new LoginThrottleService($this->db))->clear($type . ':user:' . $userId);
    }

    private function user(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: [];
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
