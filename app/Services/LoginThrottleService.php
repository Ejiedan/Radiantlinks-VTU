<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class LoginThrottleService
{
    public function __construct(private PDO $db)
    {
    }

    public function tooMany(string $bucket, int $maxAttempts = 5): bool
    {
        $stmt = $this->db->prepare('SELECT hits, resets_at FROM rate_limits WHERE bucket = ? LIMIT 1');
        $stmt->execute([$bucket]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }

        if (strtotime((string) $row['resets_at']) <= time()) {
            $this->clear($bucket);
            return false;
        }

        return (int) $row['hits'] >= $maxAttempts;
    }

    public function hit(string $bucket, int $windowMinutes = 15): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO rate_limits (bucket, hits, resets_at) VALUES (?, 1, DATE_ADD(NOW(), INTERVAL ? MINUTE))
             ON DUPLICATE KEY UPDATE hits = hits + 1'
        );
        $stmt->execute([$bucket, $windowMinutes]);
    }

    public function clear(string $bucket): void
    {
        $stmt = $this->db->prepare('DELETE FROM rate_limits WHERE bucket = ?');
        $stmt->execute([$bucket]);
    }
}
