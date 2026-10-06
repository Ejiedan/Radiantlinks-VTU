<?php

declare(strict_types=1);

namespace App\Services;

final class ProviderAuthService
{
    public static function options(): array
    {
        return [
            'none' => 'No authentication',
            'api_secret' => 'API Key + Secret headers',
            'basic' => 'Basic Auth',
            'bearer' => 'Bearer token',
            'token' => 'Token auth',
            'api_key' => 'API key header',
            'oauth_bearer' => 'OAuth client credentials',
            'custom' => 'Provider-specific / custom',
            'manual' => 'Manual / no API request',
        ];
    }

    public static function type(array $credentials, string $default = 'none'): string
    {
        $type = strtolower(trim((string) ($credentials['auth_type'] ?? '')));
        return array_key_exists($type, self::options()) ? $type : $default;
    }

    public static function headers(array $credentials, string $default = 'none'): array
    {
        $type = self::type($credentials, $default);
        $apiKey = trim((string) ($credentials['api_key'] ?? ''));
        $secretKey = trim((string) ($credentials['secret_key'] ?? ''));
        $token = trim((string) ($credentials['token'] ?? ''));

        return match ($type) {
            'basic' => ['Authorization: Basic ' . base64_encode(
                (trim((string) ($credentials['username'] ?? '')) ?: $apiKey)
                . ':' .
                (trim((string) ($credentials['password'] ?? '')) ?: $secretKey)
            )],
            'bearer' => ['Authorization: Bearer ' . ($token ?: ($secretKey ?: $apiKey))],
            'token' => ['Authorization: Token ' . ($apiKey ?: $token)],
            'api_key' => ['api-key: ' . $apiKey],
            'api_secret' => ['api-key: ' . $apiKey, 'secret-key: ' . $secretKey],
            default => [],
        };
    }

    public static function missing(array $credentials, string $default = 'none'): array
    {
        $type = self::type($credentials, $default);
        $has = static fn (string $field): bool => trim((string) ($credentials[$field] ?? '')) !== '';

        return match ($type) {
            'basic' => array_values(array_filter([
                ($has('username') || $has('api_key')) ? null : 'username or API key',
                ($has('password') || $has('secret_key')) ? null : 'password or secret key',
            ])),
            'bearer' => ($has('token') || $has('secret_key') || $has('api_key')) ? [] : ['bearer token'],
            'token' => ($has('api_key') || $has('token')) ? [] : ['API key or token'],
            'api_key' => $has('api_key') ? [] : ['API key'],
            'api_secret' => array_values(array_filter([
                $has('api_key') ? null : 'API key',
                $has('secret_key') ? null : 'secret key',
            ])),
            'oauth_bearer' => array_values(array_filter([
                $has('client_id') ? null : 'client ID',
                $has('client_secret') ? null : 'client secret',
            ])),
            default => [],
        };
    }
}
