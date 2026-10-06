<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class AuditService
{
    public function __construct(private PDO $db)
    {
    }

    public function record(?int $actorId, string $action, ?string $subjectType = null, ?int $subjectId = null, array $details = []): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO audit_logs (actor_id, action, subject_type, subject_id, ip_address, user_agent, details) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $actorId,
            $action,
            $subjectType,
            $subjectId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            json_encode($details),
        ]);
    }
}

