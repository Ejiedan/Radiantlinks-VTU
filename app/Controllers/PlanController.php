<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\HttpClient;
use App\Core\Security;
use App\Services\CommerceService;
use App\Services\ProviderPresetService;
use App\Services\ProviderAuthService;
use App\Services\ServiceCatalog;

final class PlanController
{
    public function __construct(private App $app)
    {
    }

    public function index(): void
    {
        Security::requireAdmin();
        $this->ensurePlanColumns();
        $filterService = trim((string) ($_GET['service'] ?? ''));
        if ($filterService !== '') {
            $stmt = $this->app->db()->prepare('SELECT * FROM service_plans WHERE service = ? ORDER BY service, provider, service_type, list_order, id');
            $stmt->execute([$filterService]);
            $plans = $stmt->fetchAll();
        } else {
            $plans = $this->app->db()->query('SELECT * FROM service_plans ORDER BY service, provider, service_type, list_order, id')->fetchAll();
        }
        $this->app->view('admin/plans', [
            'title' => 'Service Plans',
            'plans' => $plans,
            'services' => (new ServiceCatalog($this->app->db()))->allWithStatus(),
            'filterService' => $filterService,
            'providers' => $this->providersFor($filterService),
            'presets' => ProviderPresetService::all(),
            'packages' => (new CommerceService($this->app->db()))->packages(),
            'packagePrices' => $this->packagePricesForPlans($plans),
            'typeMappings' => $this->typeMappingsFor($filterService),
            'notice' => $_SESSION['notice'] ?? null,
        ]);
        unset($_SESSION['notice']);
    }

    public function serviceSettings(string $service): void
    {
        $_GET['service'] = $service;
        $this->index();
    }

    public function store(): void
    {
        Security::requireAdmin();
        Security::verifyCsrf();
        $this->ensurePlanColumns();

        $stmt = $this->app->db()->prepare(
            'INSERT INTO service_plans (service, provider, service_type, plan_id, name, duration, quantity, cost_price, selling_price, status, metadata, list_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE service_type = VALUES(service_type), name = VALUES(name), duration = VALUES(duration), quantity = VALUES(quantity), cost_price = VALUES(cost_price), selling_price = VALUES(selling_price), status = VALUES(status), metadata = VALUES(metadata), list_order = VALUES(list_order)'
        );
        $stmt->execute([
            trim((string) $_POST['service']),
            strtolower(trim((string) $_POST['provider'])),
            strtolower(trim((string) ($_POST['service_type'] ?? ''))),
            trim((string) $_POST['plan_id']),
            trim((string) $_POST['name']),
            trim((string) ($_POST['duration'] ?? '')),
            max(1, (int) ($_POST['quantity'] ?? 1)),
            (float) $_POST['cost_price'],
            (float) $_POST['selling_price'],
            (string) $_POST['status'],
            trim((string) ($_POST['metadata'] ?? '')) ?: '{}',
            (int) ($_POST['list_order'] ?? 0),
        ]);

        $_SESSION['notice'] = 'Service plan saved.';
        $service = trim((string) ($_POST['service'] ?? ''));
        redirect_to($service !== '' ? '/admin/service-settings/' . rawurlencode($service) : '/admin/plans');
    }

    public function update(string $id): void
    {
        Security::requireAdmin();
        Security::verifyCsrf();
        $this->ensurePlanColumns();
        $planId = (int) $id;
        $stmt = $this->app->db()->prepare(
            'UPDATE service_plans SET provider = ?, service_type = ?, plan_id = ?, name = ?, duration = ?, quantity = ?, cost_price = ?, selling_price = ?, status = ?, metadata = ?, list_order = ? WHERE id = ?'
        );
        $stmt->execute([
            strtolower(trim((string) $_POST['provider'])),
            strtolower(trim((string) ($_POST['service_type'] ?? ''))),
            trim((string) $_POST['plan_id']),
            trim((string) $_POST['name']),
            trim((string) ($_POST['duration'] ?? '')),
            max(1, (int) ($_POST['quantity'] ?? 1)),
            (float) $_POST['cost_price'],
            (float) $_POST['selling_price'],
            (string) $_POST['status'],
            trim((string) ($_POST['metadata'] ?? '')) ?: '{}',
            (int) ($_POST['list_order'] ?? 0),
            $planId,
        ]);
        $_SESSION['notice'] = 'Plan updated.';
        redirect_to('/admin/plans?service=' . urlencode((string) ($_POST['service'] ?? '')));
    }

    public function packagePrices(string $id): void
    {
        Security::requireAdmin();
        Security::verifyCsrf();
        $this->ensurePackagePriceTable();
        $planId = (int) $id;
        $this->app->db()->prepare('DELETE FROM service_plan_package_prices WHERE service_plan_id = ?')->execute([$planId]);
        $stmt = $this->app->db()->prepare('INSERT INTO service_plan_package_prices (service_plan_id, package_id, selling_price) VALUES (?, ?, ?)');
        foreach ($_POST['package_prices'] ?? [] as $packageId => $price) {
            if ((string) $price === '') {
                continue;
            }
            $stmt->execute([$planId, (int) $packageId, max(0, (float) $price)]);
        }
        $_SESSION['notice'] = 'Package prices saved.';
        redirect_to('/admin/plans?service=' . urlencode((string) ($_POST['service'] ?? '')));
    }

    public function importProviderPlans(): void
    {
        Security::requireAdmin();
        Security::verifyCsrf();
        $providerId = (int) ($_POST['provider_config_id'] ?? 0);
        $stmt = $this->app->db()->prepare('SELECT * FROM provider_configs WHERE id = ? AND service = ? LIMIT 1');
        $stmt->execute([$providerId, 'data']);
        $provider = $stmt->fetch();
        if (!$provider) {
            $_SESSION['notice'] = 'Data provider not found.';
            redirect_to('/admin/plans?service=data');
            return;
        }

        try {
            $count = $this->importTelecomAbodePlans($provider);
            $_SESSION['notice'] = 'Imported ' . $count . ' TelecomAbode data plan(s).';
        } catch (\Throwable $e) {
            $_SESSION['notice'] = $e->getMessage();
        }

        redirect_to('/admin/plans?service=data');
    }

    public function storeTypeMapping(): void
    {
        Security::requireAdmin();
        Security::verifyCsrf();
        $this->ensureTypeMappingTable();

        $service = trim((string) ($_POST['service'] ?? 'data'));
        $rawKeyword = $this->normalizeMappingKeyword((string) ($_POST['raw_keyword'] ?? ''));
        $mappedType = $this->slugType((string) ($_POST['mapped_type'] ?? ''));
        if ($service === '' || $rawKeyword === '' || $mappedType === '') {
            $_SESSION['notice'] = 'Type mapping needs a keyword and target type.';
            redirect_to('/admin/plans?service=' . urlencode($service ?: 'data'));
            return;
        }

        $stmt = $this->app->db()->prepare(
            'INSERT INTO service_type_mappings (service, raw_keyword, mapped_type, priority) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE mapped_type = VALUES(mapped_type), priority = VALUES(priority)'
        );
        $stmt->execute([$service, $rawKeyword, $mappedType, (int) ($_POST['priority'] ?? 50)]);

        if (!empty($_POST['apply_existing'])) {
            $this->remapExistingPlans($service);
        }

        $_SESSION['notice'] = 'Type mapping saved.';
        redirect_to('/admin/plans?service=' . urlencode($service));
    }

    public function deleteTypeMapping(string $id): void
    {
        Security::requireAdmin();
        Security::verifyCsrf();
        $this->ensureTypeMappingTable();
        $service = trim((string) ($_POST['service'] ?? 'data'));
        $this->app->db()->prepare('DELETE FROM service_type_mappings WHERE id = ?')->execute([(int) $id]);
        if (!empty($_POST['apply_existing'])) {
            $this->remapExistingPlans($service);
        }
        $_SESSION['notice'] = 'Type mapping deleted.';
        redirect_to('/admin/plans?service=' . urlencode($service));
    }

    public function api(string $service): void
    {
        $user = Security::requireLogin();
        header('Content-Type: application/json');
        $provider = strtolower(trim((string) ($_GET['provider'] ?? '')));
        $type = strtolower(trim((string) ($_GET['service_type'] ?? '')));
        $this->ensurePlanColumns();
        $this->ensurePackagePriceTable();
        $packageId = (int) ($user['package_id'] ?? 0);
        $sql = "SELECT p.id, p.service, p.provider, p.service_type, p.plan_id, p.name, p.duration, p.quantity,
                       COALESCE(pp.selling_price, p.selling_price) AS selling_price,
                       p.selling_price AS default_selling_price,
                       p.cost_price
                FROM service_plans p
                LEFT JOIN service_plan_package_prices pp
                  ON pp.service_plan_id = p.id AND pp.package_id = ?
                WHERE service = ? AND status = 'active'
                  AND NOT EXISTS (
                    SELECT 1 FROM service_type_controls c
                    WHERE c.status = 'off'
                      AND c.service = p.service
                      AND (c.provider = '*' OR c.provider = p.provider)
                      AND (c.service_type = '*' OR c.service_type = COALESCE(NULLIF(p.service_type,''),'*'))
                  )";
        $params = [$packageId, $service];
        if ($provider !== '') {
            $sql .= ' AND p.provider = ?';
            $params[] = $provider;
        }
        if ($type !== '') {
            $sql .= " AND (p.service_type = ? OR p.service_type IS NULL OR p.service_type = '')";
            $params[] = $type;
        }
        $sql .= ' ORDER BY provider, service_type, list_order, id';
        $stmt = $this->app->db()->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['plans' => $stmt->fetchAll()]);
    }

    private function ensurePlanColumns(): void
    {
        $this->addColumn('duration', 'VARCHAR(100) NULL AFTER name');
        $this->addColumn('quantity', 'INT UNSIGNED NOT NULL DEFAULT 1 AFTER duration');
        $this->ensurePackagePriceTable();
        $this->ensureTypeMappingTable();
    }

    private function providersFor(string $service): array
    {
        if ($service === '') {
            return [];
        }
        $stmt = $this->app->db()->prepare('SELECT * FROM provider_configs WHERE service = ? ORDER BY status, name');
        $stmt->execute([$service]);
        return $stmt->fetchAll();
    }

    private function importTelecomAbodePlans(array $provider): int
    {
        if (($provider['provider_key'] ?? '') !== 'telecomabode_normal_data') {
            throw new \RuntimeException('Automatic import is currently wired for TelecomAbode Data only.');
        }

        $credentials = json_decode((string) ($provider['credentials'] ?? '{}'), true) ?: [];
        if (ProviderAuthService::missing($credentials, 'token')) {
            throw new \RuntimeException('TelecomAbode API key is required before importing plans.');
        }

        $plansUrl = 'https://telecomabode.com.ng/api/data_plans';
        $response = (new HttpClient())->getJson($plansUrl, ProviderAuthService::headers($credentials, 'token'), 60);
        if (!$response['ok']) {
            throw new \RuntimeException('Unable to fetch TelecomAbode plans. Check API key and network connection.');
        }

        $body = is_array($response['body']) ? $response['body'] : [];
        $plans = array_is_list($body) ? $body : (array) ($body['data'] ?? $body['plans'] ?? []);
        if (!is_array($plans)) {
            throw new \RuntimeException('TelecomAbode returned an unexpected plan format.');
        }

        $this->ensurePlanColumns();
        $stmt = $this->app->db()->prepare(
            'INSERT INTO service_plans (service, provider, service_type, plan_id, name, duration, quantity, cost_price, selling_price, status, metadata, list_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE service_type = VALUES(service_type), name = VALUES(name), duration = VALUES(duration), cost_price = VALUES(cost_price), selling_price = VALUES(selling_price), status = VALUES(status), metadata = VALUES(metadata)'
        );

        $count = 0;
        foreach ($plans as $plan) {
            if (!is_array($plan) || empty($plan['plan_id'])) {
                continue;
            }
            $network = strtolower(str_replace([' ', '-'], '', (string) ($plan['network'] ?? '')));
            $network = $network === 'etisalat' ? '9mobile' : $network;
            if (!in_array($network, ['mtn', 'airtel', 'glo', '9mobile'], true)) {
                continue;
            }
            $size = trim((string) ($plan['datasize'] ?? $plan['name'] ?? 'Data Plan'));
            $duration = !empty($plan['day']) ? (string) $plan['day'] . ' days' : '';
            $price = (float) ($plan['price'] ?? 0);
            $rawType = trim((string) ($plan['type'] ?? ''));
            $type = $this->normalizeServiceType('data', $rawType, trim($size . ' ' . ($plan['name'] ?? '') . ' ' . $rawType));
            $name = trim(strtoupper($network) . ' ' . $size . ($type !== '' ? ' ' . strtoupper($type) : '') . ($duration !== '' ? ' (' . $duration . ')' : ''));
            $stmt->execute([
                'data',
                $network,
                $type,
                (string) $plan['plan_id'],
                $name,
                $duration,
                1,
                $price,
                $price,
                'active',
                json_encode(['source' => 'telecomabode', 'raw_type' => $rawType, 'mapped_type' => $type, 'raw' => $plan]),
                0,
            ]);
            $count++;
        }

        return $count;
    }

    private function addColumn(string $column, string $definition): void
    {
        $stmt = $this->app->db()->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $stmt->execute(['service_plans', $column]);
        if ((int) $stmt->fetchColumn() === 0) {
            $this->app->db()->exec('ALTER TABLE service_plans ADD COLUMN ' . $column . ' ' . $definition);
        }
    }

    private function ensurePackagePriceTable(): void
    {
        $this->app->db()->exec("CREATE TABLE IF NOT EXISTS service_plan_package_prices (
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

    private function ensureTypeMappingTable(): void
    {
        $this->app->db()->exec("CREATE TABLE IF NOT EXISTS service_type_mappings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            service VARCHAR(80) NOT NULL,
            raw_keyword VARCHAR(120) NOT NULL,
            mapped_type VARCHAR(120) NOT NULL,
            priority INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY service_type_mapping_unique (service, raw_keyword),
            KEY service_type_mapping_lookup_idx (service, priority)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $stmt = $this->app->db()->prepare(
            'INSERT IGNORE INTO service_type_mappings (service, raw_keyword, mapped_type, priority) VALUES (?, ?, ?, ?)'
        );
        foreach ($this->defaultTypeMappings() as $mapping) {
            $stmt->execute($mapping);
        }
    }

    private function defaultTypeMappings(): array
    {
        return [
            ['data', 'corporate gifting', 'corporate', 100],
            ['data', 'corporate', 'corporate', 90],
            ['data', 'cg', 'corporate', 80],
            ['data', 'sme ii', 'sme', 100],
            ['data', 'sme2', 'sme', 95],
            ['data', 'sme', 'sme', 90],
            ['data', 'gifting', 'gifting', 90],
            ['data', 'gift', 'gifting', 80],
            ['data', 'direct gifting', 'gifting', 85],
            ['data', 'awoof', 'gifting', 60],
            ['data', 'vtu', 'vtu', 50],
        ];
    }

    private function typeMappingsFor(string $service): array
    {
        if ($service === '') {
            return [];
        }
        $this->ensureTypeMappingTable();
        $stmt = $this->app->db()->prepare('SELECT * FROM service_type_mappings WHERE service = ? ORDER BY priority DESC, raw_keyword ASC');
        $stmt->execute([$service]);
        return $stmt->fetchAll();
    }

    private function normalizeServiceType(string $service, string $rawType, string $planText): string
    {
        $this->ensureTypeMappingTable();
        $haystack = $this->normalizeMappingKeyword(trim($rawType . ' ' . $planText));
        $stmt = $this->app->db()->prepare('SELECT raw_keyword, mapped_type FROM service_type_mappings WHERE service = ? ORDER BY priority DESC, LENGTH(raw_keyword) DESC, id DESC');
        $stmt->execute([$service]);
        foreach ($stmt->fetchAll() as $mapping) {
            $keyword = $this->normalizeMappingKeyword((string) $mapping['raw_keyword']);
            if ($keyword !== '' && str_contains(' ' . $haystack . ' ', ' ' . $keyword . ' ')) {
                return $this->slugType((string) $mapping['mapped_type']);
            }
        }

        if (trim($rawType) !== '') {
            return $this->slugType($rawType);
        }
        return '';
    }

    private function remapExistingPlans(string $service): void
    {
        $stmt = $this->app->db()->prepare('SELECT id, name, service_type, metadata FROM service_plans WHERE service = ?');
        $stmt->execute([$service]);
        $update = $this->app->db()->prepare('UPDATE service_plans SET service_type = ?, metadata = ? WHERE id = ?');
        foreach ($stmt->fetchAll() as $plan) {
            $metadata = json_decode((string) ($plan['metadata'] ?? '{}'), true) ?: [];
            $rawType = (string) ($metadata['raw_type'] ?? $plan['service_type'] ?? '');
            $mapped = $this->normalizeServiceType($service, $rawType, (string) ($plan['name'] ?? ''));
            $metadata['mapped_type'] = $mapped;
            $update->execute([$mapped, json_encode($metadata), (int) $plan['id']]);
        }
    }

    private function normalizeMappingKeyword(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';
        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    private function slugType(string $value): string
    {
        $slug = $this->normalizeMappingKeyword($value);
        return str_replace(' ', '_', $slug);
    }

    private function packagePricesForPlans(array $plans): array
    {
        $ids = array_values(array_filter(array_map(static fn (array $plan): int => (int) ($plan['id'] ?? 0), $plans)));
        if (empty($ids)) {
            return [];
        }
        $this->ensurePackagePriceTable();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->app->db()->prepare("SELECT * FROM service_plan_package_prices WHERE service_plan_id IN ({$placeholders})");
        $stmt->execute($ids);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int) $row['service_plan_id']][(int) $row['package_id']] = $row['selling_price'];
        }
        return $out;
    }
}
