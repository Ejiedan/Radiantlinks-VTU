<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Security;
use App\Services\ProviderPresetService;
use App\Services\ProviderHealthService;
use App\Services\ProviderAuthService;
use App\Services\ServiceCatalog;

final class ProviderController
{
    public function __construct(private App $app)
    {
    }

    public function index(): void
    {
        Security::requireAdmin();
        $providers = $this->app->db()->query('SELECT * FROM provider_configs ORDER BY service, name')->fetchAll();
        $this->app->view('admin/providers', [
            'title' => 'Providers',
            'providers' => $providers,
            'presets' => ProviderPresetService::all(),
            'services' => (new ServiceCatalog($this->app->db()))->allWithStatus(),
            'notice' => $_SESSION['notice'] ?? null,
            'noticeType' => $_SESSION['notice_type'] ?? 'success',
        ]);
        unset($_SESSION['notice'], $_SESSION['notice_type']);
    }

    public function store(): void
    {
        Security::requireAdmin();
        Security::verifyCsrf();

        $presetKey = trim((string) ($_POST['preset'] ?? ''));
        $preset = $presetKey !== '' ? ProviderPresetService::get($presetKey) : null;

        $credentials = $this->credentialsFromRequest();
        $service = (string) ($preset['service'] ?? trim((string) $_POST['service']));
        $providerKey = $presetKey ?: trim((string) $_POST['provider_key']);
        $baseUrl = (string) ($preset['base_url'] ?? trim((string) $_POST['base_url']));
        $status = (string) ($_POST['status'] ?? 'inactive');
        $credentials['auth_type'] = ProviderAuthService::type($credentials, (string) ($preset['auth_type'] ?? 'none'));
        try {
            $this->validateConfiguration($providerKey, $baseUrl, $credentials, $status);
        } catch (\Throwable $e) {
            $_SESSION['notice'] = $e->getMessage();
            $_SESSION['notice_type'] = 'error';
            $redirect = trim((string) ($_POST['redirect_to'] ?? ''));
            redirect_to($redirect !== '' ? $redirect : '/admin/providers');
            return;
        }

        $stmt = $this->app->db()->prepare(
            'INSERT INTO provider_configs (service, provider_key, name, base_url, credentials, mappings, status) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), base_url = VALUES(base_url), credentials = VALUES(credentials), mappings = VALUES(mappings), status = VALUES(status)'
        );
        $stmt->execute([
            $service,
            $providerKey,
            $preset['name'] ?? trim((string) $_POST['name']),
            $baseUrl,
            json_encode($credentials),
            trim((string) ($_POST['mappings'] ?? '')) ?: json_encode($preset['mappings'] ?? []),
            $status,
        ]);

        $_SESSION['notice'] = 'Provider configuration saved.';
        $redirect = trim((string) ($_POST['redirect_to'] ?? ''));
        redirect_to(($redirect !== '' ? $redirect : '/admin/providers'));
    }

    public function update(string $id): void
    {
        Security::requireAdmin();
        Security::verifyCsrf();
        $existing = $this->provider((int) $id);
        $credentials = $this->credentialsFromRequest(json_decode((string) ($existing['credentials'] ?? '{}'), true) ?: []);
        $providerKey = trim((string) $_POST['provider_key']);
        $preset = ProviderPresetService::get($providerKey);
        $credentials['auth_type'] = ProviderAuthService::type($credentials, (string) ($preset['auth_type'] ?? 'none'));
        $baseUrl = trim((string) $_POST['base_url']);
        $status = (string) ($_POST['status'] ?? 'inactive');
        try {
            $this->validateConfiguration($providerKey, $baseUrl, $credentials, $status);
        } catch (\Throwable $e) {
            $_SESSION['notice'] = $e->getMessage();
            $_SESSION['notice_type'] = 'error';
            $redirect = trim((string) ($_POST['redirect_to'] ?? ''));
            redirect_to($redirect !== '' ? $redirect : '/admin/providers');
            return;
        }
        $stmt = $this->app->db()->prepare('UPDATE provider_configs SET service = ?, provider_key = ?, name = ?, base_url = ?, credentials = ?, mappings = ?, status = ? WHERE id = ?');
        $stmt->execute([
            trim((string) ($_POST['service'] ?? '')),
            $providerKey,
            trim((string) $_POST['name']),
            $baseUrl,
            json_encode($credentials),
            trim((string) ($_POST['mappings'] ?? '{}')) ?: '{}',
            $status,
            (int) $id,
        ]);
        $_SESSION['notice'] = 'Provider updated.';
        $redirect = trim((string) ($_POST['redirect_to'] ?? ''));
        redirect_to(($redirect !== '' ? $redirect : '/admin/providers'));
    }

    public function delete(string $id): void
    {
        Security::requireAdmin();
        Security::verifyCsrf();
        $this->app->db()->prepare('DELETE FROM provider_configs WHERE id = ?')->execute([(int) $id]);
        $_SESSION['notice'] = 'Provider deleted.';
        $redirect = trim((string) ($_POST['redirect_to'] ?? ''));
        redirect_to(($redirect !== '' ? $redirect : '/admin/providers'));
    }

    public function test(string $id): void
    {
        Security::requireAdmin();
        Security::verifyCsrf();
        try {
            $result = (new ProviderHealthService())->test($this->provider((int) $id));
            $_SESSION['notice'] = $result['message'];
            $_SESSION['notice_type'] = $result['ok'] ? 'success' : 'error';
        } catch (\Throwable $e) {
            $_SESSION['notice'] = $e->getMessage();
            $_SESSION['notice_type'] = 'error';
        }
        $redirect = trim((string) ($_POST['redirect_to'] ?? ''));
        redirect_to($redirect !== '' ? $redirect : '/admin/providers');
    }

    private function provider(int $id): array
    {
        $stmt = $this->app->db()->prepare('SELECT * FROM provider_configs WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $provider = $stmt->fetch();
        if (!$provider) {
            throw new \RuntimeException('API configuration was not found.');
        }
        return $provider;
    }

    private function credentialsFromRequest(array $existing = []): array
    {
        $fields = ['auth_type', 'api_key', 'secret_key', 'public_key', 'contract_code', 'name_on_card', 'bank_accounts', 'token', 'username', 'password', 'client_id', 'client_secret', 'account_id', 'bank_type', 'business_id', 'bank_code', 'checkout_endpoint', 'callback_url', 'webhook_secret'];
        $sensitive = ['api_key', 'secret_key', 'public_key', 'token', 'password', 'client_secret', 'webhook_secret'];
        $credentials = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $_POST)) {
                $credentials[$field] = (string) ($existing[$field] ?? '');
                continue;
            }
            $value = trim((string) ($_POST[$field] ?? ''));
            if ($value === '' && in_array($field, $sensitive, true) && isset($existing[$field])) {
                $value = (string) $existing[$field];
            }
            $credentials[$field] = $value;
        }
        $credentials['environment'] = trim((string) ($_POST['environment'] ?? $existing['environment'] ?? 'live'));
        return $credentials;
    }

    private function validateConfiguration(string $providerKey, string $baseUrl, array $credentials, string $status): void
    {
        if ($status !== 'active') {
            return;
        }
        if ($baseUrl === '' || !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('An active API requires a valid Base URL.');
        }
        $preset = ProviderPresetService::get($providerKey);
        $authFields = ['api_key', 'secret_key', 'token', 'username', 'password', 'client_id', 'client_secret'];
        $missing = ProviderAuthService::missing($credentials, (string) ($preset['auth_type'] ?? 'none'));
        foreach ($preset['required'] ?? [] as $required) {
            if (in_array($required, $authFields, true)) {
                continue;
            }
            if (trim((string) ($credentials[$required] ?? '')) === '') {
                $missing[] = str_replace('_', ' ', $required);
            }
        }
        if ($missing) {
            throw new \InvalidArgumentException('Missing required API fields: ' . implode(', ', $missing) . '.');
        }
    }
}
