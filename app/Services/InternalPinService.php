<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class InternalPinService
{
    public function __construct(private PDO $db)
    {
        $this->ensureTable();
    }

    public function rows(): array
    {
        return $this->db->query('SELECT * FROM internal_pins ORDER BY status, service, provider, id DESC LIMIT 500')->fetchAll();
    }

    public function upload(array $input): int
    {
        $service = trim((string) ($input['service'] ?? ''));
        $provider = strtolower(trim((string) ($input['provider'] ?? '')));
        $serviceType = trim((string) ($input['service_type'] ?? 'local')) ?: 'local';
        $value = trim((string) ($input['value'] ?? ''));
        $price = (float) ($input['price'] ?? 0);
        $lines = preg_split('/\r\n|\r|\n/', trim((string) ($input['pins_data'] ?? ''))) ?: [];

        if ($service === '' || $provider === '' || $value === '' || $price <= 0 || empty($lines)) {
            throw new \InvalidArgumentException('Service, provider, value, price and PIN data are required.');
        }

        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO internal_pins (service, provider, service_type, value, price, pin, serial, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $count = 0;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', str_getcsv($line));
            $pin = (string) ($parts[0] ?? '');
            $serial = (string) ($parts[1] ?? '');
            if ($pin === '') {
                continue;
            }
            $stmt->execute([$service, $provider, $serviceType, $value, $price, $pin, $serial, 'unused']);
            $count += $stmt->rowCount() > 0 ? 1 : 0;
        }

        return $count;
    }

    public function reserve(string $service, string $provider, string $value, int $quantity = 1, string $serviceType = 'local'): array
    {
        $quantity = max(1, $quantity);
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM internal_pins
                 WHERE service = ? AND provider = ? AND value = ? AND service_type = ? AND status = 'unused'
                 ORDER BY id ASC LIMIT {$quantity} FOR UPDATE"
            );
            $stmt->execute([$service, strtolower($provider), $value, $serviceType ?: 'local']);
            $pins = $stmt->fetchAll();

            if (count($pins) < $quantity) {
                if ($ownsTransaction) {
                    $this->db->rollBack();
                }
                return [];
            }

            $ids = array_map(static fn (array $pin): int => (int) $pin['id'], $pins);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $update = $this->db->prepare("UPDATE internal_pins SET status = 'used', used_at = NOW() WHERE id IN ({$placeholders})");
            $update->execute($ids);
            if ($ownsTransaction) {
                $this->db->commit();
            }

            return array_map(static fn (array $pin): array => [
                'pin' => $pin['pin'],
                'serial' => $pin['serial'],
                'value' => $pin['value'],
            ], $pins);
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function stockSummary(): array
    {
        return $this->db->query(
            "SELECT service, provider, service_type, value, price,
                    SUM(status = 'unused') AS unused_count,
                    SUM(status = 'used') AS used_count
             FROM internal_pins
             GROUP BY service, provider, service_type, value, price
             ORDER BY service, provider, value"
        )->fetchAll();
    }

    private function ensureTable(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS internal_pins (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            service VARCHAR(80) NOT NULL,
            provider VARCHAR(80) NOT NULL,
            service_type VARCHAR(100) NOT NULL DEFAULT 'local',
            value VARCHAR(100) NOT NULL,
            price DECIMAL(20,2) NOT NULL DEFAULT 0.00,
            pin VARCHAR(255) NOT NULL,
            serial VARCHAR(255) NULL,
            status ENUM('unused','used','void') NOT NULL DEFAULT 'unused',
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY internal_pin_unique (pin),
            KEY internal_pin_lookup_idx (service, provider, value, service_type, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}
