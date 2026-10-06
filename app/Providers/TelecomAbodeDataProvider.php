<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\HttpClient;
use App\Services\ProviderAuthService;
use PDO;

final class TelecomAbodeDataProvider implements ProviderInterface
{
    public function __construct(private PDO $db, private array $config)
    {
    }

    public function purchase(string $service, array $payload, string $reference): array
    {
        if ($service !== 'data') {
            return ['status' => 'failed', 'message' => 'TelecomAbode Data can only process data purchases.', 'details' => []];
        }

        $credentials = json_decode((string) ($this->config['credentials'] ?? '{}'), true) ?: [];
        $mappings = json_decode((string) ($this->config['mappings'] ?? '{}'), true) ?: [];
        $network = strtolower(trim((string) ($payload['provider'] ?? '')));
        $mapped = strtolower((string) ($mappings[$network] ?? $network));
        $networkId = $this->networkId($mapped);
        $planId = (int) ($payload['plan_id'] ?? 0);
        $phone = preg_replace('/\D/', '', (string) ($payload['recipient'] ?? '')) ?? '';

        if ($networkId <= 0 || $planId <= 0 || $phone === '') {
            return [
                'status' => 'failed',
                'message' => 'TelecomAbode data request is missing network, phone, or plan ID.',
                'details' => compact('network', 'mapped', 'planId', 'phone'),
            ];
        }

        $url = rtrim((string) $this->config['base_url'], '/');
        $providerPayload = [
            'network' => $networkId,
            'phone' => $phone,
            'plan' => $planId,
            'plan_id' => $planId,
            'bypass' => false,
            'request-id' => $reference,
        ];
        $response = (new HttpClient())->postJson($url, $providerPayload, ProviderAuthService::headers($credentials, 'token'));

        if (!$response['ok']) {
            return [
                'status' => 'failed',
                'message' => 'TelecomAbode request failed.',
                'details' => [
                    'request_url' => $url,
                    'provider_payload' => $providerPayload,
                    'api_response' => $response,
                ],
            ];
        }

        $body = is_array($response['body']) ? $response['body'] : [];
        $status = strtolower((string) ($body['status'] ?? $body['Status'] ?? ''));
        $success = in_array($status, ['success', 'successful'], true);

        return [
            'status' => $success ? 'success' : 'failed',
            'message' => (string) ($body['message'] ?? $body['response'] ?? $body['api_response'] ?? ($success ? 'Data purchase successful.' : 'Data purchase failed.')),
            'details' => [
                'provider_reference' => $body['request-id'] ?? $reference,
                'request_url' => $url,
                'provider_payload' => $providerPayload,
                'api_response' => $body,
                'raw' => $body,
            ],
        ];
    }

    private function networkId(string $network): int
    {
        return [
            'mtn' => 1,
            '1' => 1,
            'airtel' => 2,
            '2' => 2,
            'glo' => 3,
            '3' => 3,
            '9mobile' => 4,
            'etisalat' => 4,
            '4' => 4,
        ][$network] ?? 0;
    }
}
