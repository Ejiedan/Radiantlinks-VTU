<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class PhoneNumberService
{
    public function __construct(private PDO $db)
    {
    }

    public function normalize(string $phone): string
    {
        $phone = preg_replace('/\D/', '', trim($phone)) ?? '';
        if (!preg_match('/^0\d{10}$/', $phone)) {
            throw new \InvalidArgumentException('Phone number must be exactly 11 digits and start with 0.');
        }
        return $phone;
    }

    public function assertAvailable(string $phone, ?int $exceptUserId = null): void
    {
        if ($exceptUserId === null) {
            $stmt = $this->db->prepare('SELECT id FROM users WHERE phone = ? LIMIT 1');
            $stmt->execute([$phone]);
        } else {
            $stmt = $this->db->prepare('SELECT id FROM users WHERE phone = ? AND id <> ? LIMIT 1');
            $stmt->execute([$phone, $exceptUserId]);
        }

        if ($stmt->fetchColumn() !== false) {
            throw new \InvalidArgumentException('An account already exists with this phone number.');
        }
    }
}
