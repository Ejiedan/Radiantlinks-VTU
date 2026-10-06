<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpClient;

final class ProviderHealthService
{
    public function test(array $provider): array
    {
        $key = (string) ($provider['provider_key'] ?? '');
        $name = (string) ($provider['name'] ?? $key ?: 'Provider');
        $baseUrl = trim((string) ($provider['base_url'] ?? ''));
        $credentials = json_decode((string) ($provider['credentials'] ?? '{}'), true) ?: [];
        $preset = ProviderPresetService::get($key);

        if ($baseUrl === '' || !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            return ['ok' => false, 'reachable' => false, 'message' => $name . ': Base URL is missing or invalid.'];
        }

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
            return ['ok' => false, 'reachable' => null, 'message' => $name . ': missing required fields: ' . implode(', ', $missing) . '.'];
        }

        [$url, $headers] = $this->probe($key, $baseUrl, $credentials);
        $response = (new HttpClient())->getJson($url, $headers, 20);
        $status = (int) ($response['status'] ?? 0);
        if ($status === 0) {
            return ['ok' => false, 'reachable' => false, 'message' => $name . ': could not reach ' . $url . '. ' . ((string) ($response['error'] ?? 'Connection failed.'))];
        }
        if (in_array($status, [401, 403], true)) {
            return ['ok' => false, 'reachable' => true, 'message' => $name . ': endpoint reached, but credentials were rejected (HTTP ' . $status . ').'];
        }
        if ($status >= 500) {
            return ['ok' => false, 'reachable' => true, 'message' => $name . ': endpoint reached, but the provider returned HTTP ' . $status . '.'];
        }

        $detail = $status >= 200 && $status < 300
            ? 'connection and credentials accepted'
            : 'endpoint responded HTTP ' . $status . ' (host is reachable)';
        return ['ok' => true, 'reachable' => true, 'message' => $name . ': ' . $detail . '.'];
    }

    private function probe(string $key, string $baseUrl, array $credentials): array
    {
        $preset = ProviderPresetService::get($key);
        if ($key === 'telecomabode_normal_data') {
            return ['https://telecomabode.com.ng/api/data_plans', ProviderAuthService::headers($credentials, 'token')];
        }
        if (str_starts_with($key, 'vtpass_')) {
            return [rtrim($baseUrl, '/') . '/balance', ProviderAuthService::headers($credentials, 'api_secret')];
        }
        if (str_starts_with($key, 'lizzysub_')) {
            return [rtrim($baseUrl, '/') . '/api/user', ProviderAuthService::headers($credentials, 'token')];
        }
        if (str_starts_with($key, 'paypoint_')) {
            return [rtrim($baseUrl, '/') . '/', ProviderAuthService::headers($credentials, 'token')];
        }
        if ($key === 'securewave') {
            return [rtrim($baseUrl, '/') . '/', [
                'Authorization: Bearer ' . (string) ($credentials['secret_key'] ?? ''),
                'x-api-key: ' . (string) ($credentials['public_key'] ?? ''),
            ]];
        }
        if ($key === 'paymentpoint') {
            return [rtrim($baseUrl, '/') . '/', ['Authorization: Bearer ' . (string) ($credentials['token'] ?? '')]];
        }
        return [rtrim($baseUrl, '/') . '/', ProviderAuthService::headers($credentials, (string) ($preset['auth_type'] ?? 'none'))];
    }
}
