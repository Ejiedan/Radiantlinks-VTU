<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\HttpClient;
use App\Services\ProviderAuthService;
use PDO;

final class LizzySubProvider implements ProviderInterface
{
    public function __construct(private PDO $db, private array $config)
    {
    }

    public function purchase(string $service, array $payload, string $reference): array
    {
        return match ($service) {
            'airtime' => $this->purchaseAirtime($payload, $reference),
            'data' => $this->purchaseData($payload, $reference),
            'electricity' => $this->purchaseElectricity($payload, $reference),
            default => [
                'status' => 'failed',
                'message' => 'LizzySub provider is not configured for this service.',
                'details' => ['service' => $service],
            ],
        };
    }

    public function verifyCustomer(string $service, string $provider, string $recipient, string $planId = ''): array
    {
        if ($service !== 'electricity') {
            throw new \RuntimeException('LizzySub verification is currently available for electricity meters.');
        }

        $payload = [
            'meter_number' => trim($recipient),
            'disco' => $this->mappedValue(strtolower($provider), $this->electricityFallbackMappings()),
            'meter_type' => in_array(strtolower($planId), ['prepaid', 'postpaid'], true) ? strtolower($planId) : 'prepaid',
        ];
        $response = $this->post('/api/bill/bill-validation', $payload);
        $body = is_array($response['body'] ?? null) ? $response['body'] : [];
        if (!$response['ok'] || strtolower((string) ($body['status'] ?? '')) !== 'success') {
            throw new \RuntimeException($this->messageFromBody($body, 'Unable to verify meter from LizzySub.'));
        }

        return [
            'ok' => true,
            'name' => (string) ($body['name'] ?? $body['customer_name'] ?? ''),
            'address' => (string) ($body['address'] ?? ''),
            'message' => (string) ($body['message'] ?? 'Verification successful.'),
            'raw' => $body,
            'request' => $payload,
        ];
    }

    private function purchaseAirtime(array $payload, string $reference): array
    {
        $network = strtolower(trim((string) ($payload['provider'] ?? '')));
        $phone = preg_replace('/\D/', '', (string) ($payload['recipient'] ?? '')) ?? '';
        $amount = (float) ($payload['amount'] ?? 0);
        $providerPayload = [
            'network' => $this->mappedValue($network, ['mtn' => '1', 'airtel' => '2', 'glo' => '3', '9mobile' => '4']),
            'phone' => $phone,
            'plan_type' => strtoupper((string) ($payload['service_type'] ?? 'VTU')),
            'bypass' => false,
            'amount' => $amount,
            'request-id' => 'Airtime_' . $reference,
        ];
        return $this->postPurchase('/api/topup', $providerPayload, 'LizzySub airtime purchase failed.');
    }

    private function purchaseData(array $payload, string $reference): array
    {
        $network = strtolower(trim((string) ($payload['provider'] ?? '')));
        $phone = preg_replace('/\D/', '', (string) ($payload['recipient'] ?? '')) ?? '';
        $providerPayload = [
            'network' => $this->mappedValue($network, ['mtn' => '1', 'airtel' => '2', 'glo' => '3', '9mobile' => '4']),
            'phone' => $phone,
            'data_plan' => trim((string) ($payload['plan_id'] ?? '')),
            'bypass' => false,
            'request-id' => 'Data_' . $reference,
        ];
        return $this->postPurchase('/api/data', $providerPayload, 'LizzySub data purchase failed.');
    }

    private function purchaseElectricity(array $payload, string $reference): array
    {
        $provider = strtolower(trim((string) ($payload['provider'] ?? '')));
        $meterType = strtolower(trim((string) ($payload['plan_id'] ?? 'prepaid'))) ?: 'prepaid';
        $providerPayload = [
            'disco' => $this->mappedValue($provider, $this->electricityFallbackMappings()),
            'meter_type' => in_array($meterType, ['prepaid', 'postpaid'], true) ? $meterType : 'prepaid',
            'meter_number' => trim((string) ($payload['recipient'] ?? '')),
            'amount' => (float) ($payload['amount'] ?? 0),
            'bypass' => false,
            'request-id' => 'Bill_' . $reference,
        ];
        return $this->postPurchase('/api/bill', $providerPayload, 'LizzySub electricity purchase failed.');
    }

    private function postPurchase(string $path, array $providerPayload, string $fallback): array
    {
        $response = $this->post($path, $providerPayload);
        $body = is_array($response['body'] ?? null) ? $response['body'] : [];
        $success = strtolower((string) ($body['status'] ?? '')) === 'success';
        $token = (string) ($body['token'] ?? $body['purchased_code'] ?? '');

        return [
            'status' => $success ? 'success' : 'failed',
            'message' => $this->messageFromBody($body, $fallback),
            'details' => [
                'provider_reference' => $body['request-id'] ?? $body['transid'] ?? $providerPayload['request-id'] ?? null,
                'request_url' => $this->url($path),
                'provider_payload' => $providerPayload,
                'api_response' => $response,
                'raw' => $body,
                'token' => $token,
                'old_balance' => $body['oldbal'] ?? null,
                'new_balance' => $body['newbal'] ?? null,
            ],
        ];
    }

    private function post(string $path, array $payload): array
    {
        return (new HttpClient())->postJson($this->url($path), $payload, $this->headers(), 60);
    }

    private function url(string $path): string
    {
        $base = rtrim((string) ($this->config['base_url'] ?? 'https://app.lizzysub.com'), '/');
        return $base . '/' . ltrim($path, '/');
    }

    private function headers(): array
    {
        $credentials = json_decode((string) ($this->config['credentials'] ?? '{}'), true) ?: [];
        return ProviderAuthService::headers($credentials, 'token');
    }

    private function mappedValue(string $key, array $fallback): string
    {
        $mappings = json_decode((string) ($this->config['mappings'] ?? '{}'), true) ?: [];
        return (string) ($mappings[$key] ?? $fallback[$key] ?? $key);
    }

    private function electricityFallbackMappings(): array
    {
        return [
            'ikeja' => '1',
            'eko' => '2',
            'abuja' => '3',
            'kano' => '4',
            'portharcourt' => '5',
            'jos' => '6',
            'ibadan' => '7',
            'kaduna' => '8',
            'enugu' => '9',
            'benin' => '10',
            'aba' => '11',
            'yola' => '12',
        ];
    }

    private function messageFromBody(array $body, string $fallback): string
    {
        return (string) ($body['message'] ?? $body['response'] ?? $body['api_response'] ?? $fallback);
    }
}
