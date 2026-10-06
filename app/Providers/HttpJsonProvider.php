<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\HttpClient;
use App\Services\ProviderAuthService;
use PDO;

final class HttpJsonProvider implements ProviderInterface
{
    public function __construct(private PDO $db, private array $config)
    {
    }

    public function purchase(string $service, array $payload, string $reference): array
    {
        $url = rtrim((string) ($this->config['base_url'] ?? ''), '/') . '/purchase';
        $credentials = json_decode((string) ($this->config['credentials'] ?? '{}'), true) ?: [];
        $headers = ProviderAuthService::headers($credentials, 'bearer');

        $providerPayload = [
            'service' => $service,
            'reference' => $reference,
            'payload' => $payload,
        ];
        unset($providerPayload['payload']['pin'], $providerPayload['payload']['_csrf']);

        $response = (new HttpClient())->postJson($url, $providerPayload, $headers);

        if (!$response['ok']) {
            return [
                'status' => 'failed',
                'message' => 'Provider request failed',
                'details' => [
                    'request_url' => $url,
                    'provider_payload' => $providerPayload,
                    'api_response' => $response,
                ],
            ];
        }

        $body = is_array($response['body']) ? $response['body'] : [];
        return [
            'status' => (($body['status'] ?? '') === 'success') ? 'success' : 'failed',
            'message' => (string) ($body['message'] ?? 'Provider response received'),
            'details' => [
                'request_url' => $url,
                'provider_payload' => $providerPayload,
                'api_response' => $body,
                'raw' => $body,
            ],
        ];
    }
}
