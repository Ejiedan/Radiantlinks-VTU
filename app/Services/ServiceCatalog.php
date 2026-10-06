<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class ServiceCatalog
{
    public const SERVICES = [
        'airtime' => 'Airtime',
        'data' => 'Data',
        'cable' => 'Cable TV',
        'electricity' => 'Electricity',
        'exam_epin' => 'Exam ePIN',
        'data_pin' => 'Data PIN',
        'airtime_pin' => 'Airtime PIN',
    ];

    public function __construct(private PDO $db)
    {
    }

    public function allWithStatus(): array
    {
        $settings = new SettingsService($this->db);
        $rows = [];
        foreach (self::SERVICES as $key => $label) {
            $rows[] = [
                'key' => $key,
                'label' => $label,
                'status' => (string) $settings->get('service_' . $key . '_status', 'on'),
            ];
        }
        $this->ensureTable();
        foreach ($this->db->query('SELECT service_key, label, status FROM services ORDER BY label')->fetchAll() as $service) {
            if (array_key_exists($service['service_key'], self::SERVICES)) {
                continue;
            }
            $rows[] = [
                'key' => $service['service_key'],
                'label' => $service['label'],
                'status' => $service['status'],
            ];
        }
        return $rows;
    }

    public function isEnabled(string $service): bool
    {
        if (array_key_exists($service, self::SERVICES)) {
            return (new SettingsService($this->db))->get('service_' . $service . '_status', 'on') === 'on';
        }

        $this->ensureTable();
        $stmt = $this->db->prepare('SELECT status FROM services WHERE service_key = ? LIMIT 1');
        $stmt->execute([$service]);
        return $stmt->fetchColumn() === 'on';
    }

    public function saveCustom(string $key, string $label, string $status): void
    {
        $this->ensureTable();
        $key = strtolower(preg_replace('/[^a-z0-9_]/', '_', trim($key)) ?? '');
        if ($key === '') {
            throw new \InvalidArgumentException('Service key is required.');
        }
        $stmt = $this->db->prepare('INSERT INTO services (service_key, label, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE label = VALUES(label), status = VALUES(status)');
        $stmt->execute([$key, trim($label), $status === 'on' ? 'on' : 'off']);
    }

    public function typeControls(): array
    {
        $this->ensureTable();
        $this->ensureTypeTable();
        return $this->db->query('SELECT * FROM service_type_controls ORDER BY service, provider, service_type')->fetchAll();
    }

    public function saveTypeControl(string $service, string $provider, string $type, string $status): void
    {
        $this->ensureTypeTable();
        $stmt = $this->db->prepare('INSERT INTO service_type_controls (service, provider, service_type, status) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)');
        $stmt->execute([trim($service), trim($provider) ?: '*', trim($type) ?: '*', $status === 'on' ? 'on' : 'off']);
    }

    private function ensureTable(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS services (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            service_key VARCHAR(80) NOT NULL,
            label VARCHAR(150) NOT NULL,
            status ENUM('on','off') NOT NULL DEFAULT 'on',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY services_key_unique (service_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private function ensureTypeTable(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS service_type_controls (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            service VARCHAR(80) NOT NULL,
            provider VARCHAR(80) NOT NULL DEFAULT '*',
            service_type VARCHAR(100) NOT NULL DEFAULT '*',
            status ENUM('on','off') NOT NULL DEFAULT 'on',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY service_type_unique (service, provider, service_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}
