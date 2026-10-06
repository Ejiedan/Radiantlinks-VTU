<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class SettingsService
{
    private array $cache = [];

    public function __construct(private PDO $db)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $stmt = $this->db->prepare('SELECT `value` FROM settings WHERE `key` = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();

        if ($value === false) {
            return $default;
        }

        $decoded = json_decode((string) $value, true);
        return $this->cache[$key] = json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    public function set(string $key, mixed $value): void
    {
        $stored = is_array($value) ? json_encode($value) : (string) $value;
        $stmt = $this->db->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');
        $stmt->execute([$key, $stored]);
        $this->cache[$key] = $value;
    }

    public function many(): array
    {
        $rows = $this->db->query('SELECT `key`, `value` FROM settings')->fetchAll();
        $settings = [];
        foreach ($rows as $row) {
            $decoded = json_decode((string) $row['value'], true);
            $settings[$row['key']] = json_last_error() === JSON_ERROR_NONE ? $decoded : $row['value'];
        }
        return $settings;
    }
}

