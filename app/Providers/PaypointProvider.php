<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\HttpClient;
use App\Services\ProviderAuthService;
use PDO;

final class PaypointProvider implements ProviderInterface
{
    public function __construct(private PDO $db, private array $config)
    {
    }

    public function purchase(string $service, array $payload, string $reference): array
    {
        return match ($service) {
            'data' => $this->purchaseData($payload),
            'airtime' => $this->purchaseAirtime($payload),
            'exam_epin' => $this->purchaseExam($payload),
            'cable' => $this->purchaseCable($payload),
            'electricity' => $this->purchaseElectricity($payload),
            default => [
                'status' => 'failed',
                'message' => 'Paypoint provider is not configured for this service.',
                'details' => ['service' => $service, 'reference' => $reference],
            ],
        };
    }

    public function verifyCustomer(string $service, string $provider, string $recipient, string $planId = ''): array
    {
        if ($service === 'cable') {
            $payload = [
                'cablename' => $this->mappedValue(strtolower($provider), ['dstv' => 'dstv', 'gotv' => 'gotv', 'startimes' => 'startimes']),
                'iuc_number' => trim($recipient),
            ];
            return $this->verify('/api/validate/iuc/', $payload, 'Unable to validate IUC from Paypoint.');
        }

        if ($service === 'electricity') {
            $payload = [
                'disco_name' => $this->mappedValue(strtolower($provider), $this->electricityFallbackMappings()),
                'meter_number' => trim($recipient),
            ];
            return $this->verify('/api/validate/meter/', $payload, 'Unable to validate meter from Paypoint.');
        }

        throw new \RuntimeException('Paypoint verification is available for cable and electricity only.');
    }

    private function purchaseData(array $payload): array
    {
        $network = strtolower(trim((string) ($payload['provider'] ?? '')));
        $providerPayload = [
            'type' => $this->mappedValue($network, ['mtn' => '1', 'airtel' => '2', 'glo' => '3', '9mobile' => '4']),
            'mobile_number' => preg_replace('/\D/', '', (string) ($payload['recipient'] ?? '')),
            'plan' => trim((string) ($payload['plan_id'] ?? '')),
        ];
        return $this->postPurchase('/api/data/', $providerPayload, 'Paypoint data purchase failed.');
    }

    private function purchaseAirtime(array $payload): array
    {
        $network = strtolower(trim((string) ($payload['provider'] ?? '')));
        $providerPayload = [
            'network' => $this->mappedValue($network, ['mtn' => 'MTN', 'airtel' => 'AIRTEL', 'glo' => 'GLO', '9mobile' => 'ETISALAT']),
            'mobile_number' => preg_replace('/\D/', '', (string) ($payload['recipient'] ?? '')),
            'amount' => (float) ($payload['amount'] ?? 0),
        ];
        return $this->postPurchase('/api/airtime/', $providerPayload, 'Paypoint airtime purchase failed.');
    }

    private function purchaseExam(array $payload): array
    {
        $providerPayload = [
            'exam_type' => trim((string) ($payload['plan_id'] ?? $payload['provider'] ?? '')),
            'quantity' => (string) max(1, (int) ($payload['quantity'] ?? 1)),
            'mobile_number' => preg_replace('/\D/', '', (string) ($payload['recipient'] ?? '')),
        ];
        return $this->postPurchase('/api/exam/', $providerPayload, 'Paypoint exam PIN purchase failed.');
    }

    private function purchaseCable(array $payload): array
    {
        $provider = strtolower(trim((string) ($payload['provider'] ?? '')));
        $providerPayload = [
            'cablename' => $this->mappedValue($provider, ['dstv' => 'dstv', 'gotv' => 'gotv', 'startimes' => 'startimes']),
            'smart_card_number' => trim((string) ($payload['recipient'] ?? '')),
            'iuc_number' => trim((string) ($payload['recipient'] ?? '')),
            'cableplan' => trim((string) ($payload['plan_id'] ?? '')),
            'mobile_number' => preg_replace('/\D/', '', (string) ($payload['customer_phone'] ?? $payload['phone'] ?? $payload['recipient'] ?? '')),
        ];
        return $this->postPurchase('/api/cable/', $providerPayload, 'Paypoint cable purchase failed.');
    }

    private function purchaseElectricity(array $payload): array
    {
        $provider = strtolower(trim((string) ($payload['provider'] ?? '')));
        $meterType = strtolower(trim((string) ($payload['plan_id'] ?? 'prepaid'))) ?: 'prepaid';
        $providerPayload = [
            'disco_name' => $this->mappedValue($provider, $this->electricityFallbackMappings()),
            'meter_number' => trim((string) ($payload['recipient'] ?? '')),
            'meter_type' => in_array($meterType, ['prepaid', 'postpaid'], true) ? $meterType : 'prepaid',
            'amount' => (float) ($payload['amount'] ?? 0),
            'mobile_number' => preg_replace('/\D/', '', (string) ($payload['customer_phone'] ?? $payload['phone'] ?? '')),
        ];
        return $this->postPurchase('/api/bill/', $providerPayload, 'Paypoint electricity purchase failed.');
    }

    private function verify(string $path, array $providerPayload, string $fallback): array
    {
        $response = $this->post($path, $providerPayload);
        $body = is_array($response['body'] ?? null) ? $response['body'] : [];
        if (!$response['ok'] || $this->isFailed($body)) {
            throw new \RuntimeException($this->messageFromBody($body, $fallback));
        }

        return [
            'ok' => true,
            'name' => (string) ($body['name'] ?? $body['customer_name'] ?? $body['customerName'] ?? $body['Customer_Name'] ?? ''),
            'address' => (string) ($body['address'] ?? $body['customer_address'] ?? ''),
            'message' => $this->messageFromBody($body, 'Verification successful.'),
            'raw' => $body,
            'request' => $providerPayload,
        ];
    }

    private function postPurchase(string $path, array $providerPayload, string $fallback): array
    {
        $response = $this->post($path, $providerPayload);
        $body = is_array($response['body'] ?? null) ? $response['body'] : [];
        $success = $response['ok'] && !$this->isFailed($body);

        return [
            'status' => $success ? 'success' : 'failed',
            'message' => $this->messageFromBody($body, $success ? 'Paypoint transaction successful.' : $fallback),
            'details' => [
                'request_url' => $this->url($path),
                'provider_payload' => $providerPayload,
                'api_response' => $response,
                'raw' => $body,
                'provider_reference' => $body['reference'] ?? $body['request-id'] ?? $body['ident'] ?? null,
                'token' => (string) ($body['token'] ?? $body['Token'] ?? ''),
                'pins' => $this->pinsFromBody($body),
                'customer_name' => $body['name'] ?? $body['customer_name'] ?? null,
                'customer_address' => $body['address'] ?? $body['customer_address'] ?? null,
            ],
        ];
    }

    private function post(string $path, array $payload): array
    {
        return (new HttpClient())->postJson($this->url($path), $payload, $this->headers(), 60);
    }

    private function url(string $path): string
    {
        return rtrim((string) ($this->config['base_url'] ?? ''), '/') . '/' . ltrim($path, '/');
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
            'abuja' => 'abuja',
            'eko' => 'eko',
            'ikeja' => 'ikeja',
            'ibadan' => 'ibadan',
            'kano' => 'kano',
            'jos' => 'jos',
            'kaduna' => 'kaduna',
            'enugu' => 'enugu',
            'portharcourt' => 'portharcourt',
            'benin' => 'benin',
            'aba' => 'aba',
            'yola' => 'yola',
        ];
    }

    private function isFailed(array $body): bool
    {
        $status = strtolower((string) ($body['status'] ?? $body['Status'] ?? 'success'));
        return in_array($status, ['failed', 'fail', 'error', 'false'], true);
    }

    private function messageFromBody(array $body, string $fallback): string
    {
        return (string) ($body['message'] ?? $body['response'] ?? $body['api_response'] ?? $body['detail'] ?? $fallback);
    }

    private function pinsFromBody(array $body): array
    {
        $pins = $body['pins'] ?? $body['tokens'] ?? [];
        if (!is_array($pins)) {
            return [];
        }
        return array_map(static function (mixed $pin): array {
            if (is_array($pin)) {
                return ['pin' => (string) ($pin['pin'] ?? $pin['token'] ?? ''), 'serial' => (string) ($pin['serial'] ?? '')];
            }
            return ['pin' => (string) $pin, 'serial' => ''];
        }, $pins);
    }
}
