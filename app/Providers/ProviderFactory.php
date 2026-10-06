<?php

declare(strict_types=1);

namespace App\Providers;

use PDO;

final class ProviderFactory
{
    public static function make(PDO $db, string $service): ProviderInterface
    {
        if (in_array($service, ['airtime_pin', 'data_pin', 'exam_epin', 'recharge_card'], true) && self::hasUnusedPins($db, $service)) {
            return new InternalPinProvider($db);
        }

        $stmt = $db->prepare("SELECT * FROM provider_configs WHERE service = ? AND status = 'active' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$service]);
        $config = $stmt->fetch();

        if ($config && !empty($config['base_url'])) {
            if ($config['provider_key'] === 'telecomabode_normal_data') {
                return new TelecomAbodeDataProvider($db, $config);
            }
            if (in_array($config['provider_key'], ['vtpass_airtime', 'vtpass_exam_epin', 'vtpass_cable', 'vtpass_electricity'], true)) {
                return new VtpassProvider($db, $config);
            }
            if (in_array($config['provider_key'], ['lizzysub_airtime', 'lizzysub_data', 'lizzysub_electricity'], true)) {
                return new LizzySubProvider($db, $config);
            }
            if (in_array($config['provider_key'], ['paypoint_data', 'paypoint_airtime', 'paypoint_exam_epin', 'paypoint_cable', 'paypoint_electricity'], true)) {
                return new PaypointProvider($db, $config);
            }
            if (in_array($config['provider_key'], ['stepupconnect_exam', 'telecomabode_data', 'stepupconnect_recharge', 'telecomabode_recharge', 'cardpin'], true)) {
                return new VtupressPinProvider($db, $config);
            }
            return new HttpJsonProvider($db, $config);
        }

        return new DemoProvider($db);
    }

    public static function makeForPayload(PDO $db, string $service, array $payload): ProviderInterface
    {
        if (in_array($service, ['airtime_pin', 'data_pin', 'exam_epin', 'recharge_card'], true)) {
            $provider = strtolower(trim((string) ($payload['provider'] ?? '')));
            $value = trim((string) ($payload['plan_id'] ?? $payload['value'] ?? $payload['amount'] ?? ''));
            $type = trim((string) ($payload['service_type'] ?? 'local')) ?: 'local';
            if ($provider !== '' && $value !== '' && self::hasMatchingPins($db, $service, $provider, $value, $type)) {
                return new InternalPinProvider($db);
            }
        }

        return self::make($db, $service);
    }

    private static function hasUnusedPins(PDO $db, string $service): bool
    {
        self::ensureInternalPinTable($db);
        $stmt = $db->prepare("SELECT COUNT(*) FROM internal_pins WHERE service = ? AND status = 'unused'");
        $stmt->execute([$service]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private static function hasMatchingPins(PDO $db, string $service, string $provider, string $value, string $type): bool
    {
        self::ensureInternalPinTable($db);
        $stmt = $db->prepare("SELECT COUNT(*) FROM internal_pins WHERE service = ? AND provider = ? AND value = ? AND service_type = ? AND status = 'unused'");
        $stmt->execute([$service, $provider, $value, $type]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private static function ensureInternalPinTable(PDO $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS internal_pins (
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
