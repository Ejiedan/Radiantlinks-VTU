<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\HttpClient;
use App\Services\ProviderAuthService;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class VtpassProvider implements ProviderInterface
{
    public function __construct(private PDO $db, private array $config)
    {
    }

    public function purchase(string $service, array $payload, string $reference): array
    {
        return match ($service) {
            'airtime' => $this->purchaseAirtime($payload, $reference),
            'cable' => $this->purchaseCable($payload, $reference),
            'electricity' => $this->purchaseElectricity($payload, $reference),
            'exam_epin' => $this->purchaseEducationPin($payload, $reference),
            default => [
                'status' => 'failed',
                'message' => 'VTpass provider is not configured for this service.',
                'details' => ['service' => $service],
            ],
        };
    }

    public function verifyCustomer(string $service, string $provider, string $recipient, string $planId = ''): array
    {
        $serviceId = match ($service) {
            'cable' => $this->mappedValue(strtolower($provider), [
                'dstv' => 'dstv',
                'gotv' => 'gotv',
                'startimes' => 'startimes',
            ]),
            'electricity' => $this->mappedValue(strtolower($provider), $this->electricityFallbackMappings()),
            default => '',
        };

        if ($serviceId === '' || trim($recipient) === '') {
            throw new \RuntimeException('Provider and customer number are required.');
        }

        $payload = [
            'billersCode' => trim($recipient),
            'serviceID' => $serviceId,
        ];
        if ($service === 'electricity') {
            $payload['type'] = in_array(strtolower($planId), ['postpaid', 'prepaid'], true) ? strtolower($planId) : 'prepaid';
        }

        $url = rtrim((string) ($this->config['base_url'] ?? 'https://vtpass.com/api'), '/') . '/merchant-verify';
        $response = (new HttpClient())->postJson($url, $payload, $this->postHeaders(), 45);
        $body = is_array($response['body'] ?? null) ? $response['body'] : [];
        if (!$response['ok'] || (string) ($body['code'] ?? '') !== '000') {
            throw new \RuntimeException($this->messageFromBody($body, 'Unable to verify customer from VTpass.'));
        }

        $content = is_array($body['content'] ?? null) ? $body['content'] : [];
        return [
            'ok' => true,
            'name' => (string) ($content['Customer_Name'] ?? $content['CustomerName'] ?? $content['customerName'] ?? ''),
            'address' => (string) ($content['Address'] ?? $content['customerAddress'] ?? ''),
            'message' => 'Verification successful.',
            'raw' => $body,
            'request' => $payload,
        ];
    }

    private function purchaseAirtime(array $payload, string $reference): array
    {
        $network = strtolower(trim((string) ($payload['provider'] ?? '')));
        $phone = preg_replace('/\D/', '', (string) ($payload['recipient'] ?? '')) ?? '';
        $amount = (float) ($payload['amount'] ?? 0);
        $serviceId = $this->mappedValue($network, [
            'mtn' => 'mtn',
            'airtel' => 'airtel',
            'glo' => 'glo',
            '9mobile' => 'etisalat',
            'etisalat' => 'etisalat',
        ]);

        if ($serviceId === '' || $phone === '' || $amount < 50) {
            return [
                'status' => 'failed',
                'message' => 'VTpass airtime needs a network, valid phone number, and minimum amount of 50.',
                'details' => compact('network', 'serviceId', 'phone', 'amount'),
            ];
        }

        return $this->postPay([
            'request_id' => $this->vtpassRequestId($reference),
            'serviceID' => $serviceId,
            'amount' => $amount,
            'phone' => $phone,
        ], 'VTpass airtime purchase failed.');
    }

    private function purchaseEducationPin(array $payload, string $reference): array
    {
        $exam = strtolower(trim((string) ($payload['provider'] ?? '')));
        $phone = preg_replace('/\D/', '', (string) ($payload['recipient'] ?? '')) ?? '';
        $variationCode = trim((string) ($payload['plan_id'] ?? ''));
        $amount = (float) ($payload['amount'] ?? 0);
        $serviceId = $this->mappedValue($exam, [
            'waec' => 'waec-registration',
            'waec-registration' => 'waec-registration',
            'waec_result' => 'waec',
            'waec-result' => 'waec',
            'jamb' => 'jamb',
            'neco' => 'neco',
            'nabteb' => 'nabteb',
        ]);

        if ($serviceId === '' || $phone === '' || $variationCode === '') {
            return [
                'status' => 'failed',
                'message' => 'VTpass education PIN needs exam body, customer phone, and variation code.',
                'details' => compact('exam', 'serviceId', 'phone', 'variationCode'),
            ];
        }

        return $this->postPay([
            'request_id' => $this->vtpassRequestId($reference),
            'serviceID' => $serviceId,
            'billersCode' => $phone,
            'variation_code' => $variationCode,
            'amount' => $amount,
            'phone' => $phone,
        ], 'VTpass education PIN purchase failed.');
    }

    private function purchaseCable(array $payload, string $reference): array
    {
        $provider = strtolower(trim((string) ($payload['provider'] ?? '')));
        $smartcard = trim((string) ($payload['recipient'] ?? ''));
        $variationCode = trim((string) ($payload['plan_id'] ?? ''));
        $amount = (float) ($payload['amount'] ?? 0);
        $phone = preg_replace('/\D/', '', (string) ($payload['customer_phone'] ?? $payload['phone'] ?? '')) ?? '';
        $serviceId = $this->mappedValue($provider, [
            'dstv' => 'dstv',
            'gotv' => 'gotv',
            'startimes' => 'startimes',
        ]);

        if ($serviceId === '' || $smartcard === '' || $variationCode === '' || $phone === '') {
            return [
                'status' => 'failed',
                'message' => 'VTpass cable purchase needs provider, smartcard/IUC, package, and customer phone.',
                'details' => compact('provider', 'serviceId', 'smartcard', 'variationCode', 'phone'),
            ];
        }

        return $this->postPay([
            'request_id' => $this->vtpassRequestId($reference),
            'serviceID' => $serviceId,
            'billersCode' => $smartcard,
            'variation_code' => $variationCode,
            'amount' => $amount,
            'phone' => $phone,
            'subscription_type' => (string) ($payload['subscription_type'] ?? 'change'),
            'quantity' => max(1, (int) ($payload['quantity'] ?? 1)),
        ], 'VTpass cable purchase failed.');
    }

    private function purchaseElectricity(array $payload, string $reference): array
    {
        $provider = strtolower(trim((string) ($payload['provider'] ?? '')));
        $meter = trim((string) ($payload['recipient'] ?? ''));
        $meterType = strtolower(trim((string) ($payload['plan_id'] ?? 'prepaid'))) ?: 'prepaid';
        $meterType = in_array($meterType, ['prepaid', 'postpaid'], true) ? $meterType : 'prepaid';
        $amount = (float) ($payload['amount'] ?? 0);
        $phone = preg_replace('/\D/', '', (string) ($payload['customer_phone'] ?? $payload['phone'] ?? '')) ?? '';
        $serviceId = $this->mappedValue($provider, $this->electricityFallbackMappings());

        if ($serviceId === '' || $meter === '' || $amount <= 0 || $phone === '') {
            return [
                'status' => 'failed',
                'message' => 'VTpass electricity purchase needs disco, meter number, amount, meter type, and customer phone.',
                'details' => compact('provider', 'serviceId', 'meter', 'meterType', 'amount', 'phone'),
            ];
        }

        return $this->postPay([
            'request_id' => $this->vtpassRequestId($reference),
            'serviceID' => $serviceId,
            'billersCode' => $meter,
            'variation_code' => $meterType,
            'amount' => $amount,
            'phone' => $phone,
        ], 'VTpass electricity purchase failed.');
    }

    private function postPay(array $providerPayload, string $fallbackFailureMessage): array
    {
        $url = rtrim((string) ($this->config['base_url'] ?? 'https://vtpass.com/api'), '/') . '/pay';
        $response = (new HttpClient())->postJson($url, $providerPayload, $this->postHeaders(), 60);
        if (!$response['ok']) {
            return [
                'status' => 'failed',
                'message' => $this->messageFromBody($response['body'] ?? null, $fallbackFailureMessage),
                'details' => [
                    'request_url' => $url,
                    'provider_payload' => $providerPayload,
                    'api_response' => $response,
                ],
            ];
        }

        $body = is_array($response['body']) ? $response['body'] : [];
        $code = (string) ($body['code'] ?? '');
        $transactionStatus = strtolower((string) ($body['content']['transactions']['status'] ?? ''));
        $success = $code === '000' && ($transactionStatus === '' || in_array($transactionStatus, ['delivered', 'successful', 'success'], true));
        $pins = $this->pinsFromBody($body);
        $token = $this->tokenFromBody($body);

        return [
            'status' => $success ? 'success' : 'failed',
            'message' => $this->messageFromBody($body, $success ? 'VTpass transaction successful.' : $fallbackFailureMessage),
            'details' => [
                'provider_reference' => $body['requestId'] ?? $providerPayload['request_id'],
                'request_url' => $url,
                'provider_payload' => $providerPayload,
                'api_response' => $body,
                'raw' => $body,
                'pins' => $pins,
                'token' => $token,
                'purchased_code' => $body['purchased_code'] ?? '',
                'customer_name' => $body['customerName'] ?? $body['customer_name'] ?? null,
                'customer_address' => $body['customerAddress'] ?? $body['customer_address'] ?? null,
                'meter_number' => $body['meterNumber'] ?? null,
            ],
        ];
    }

    private function postHeaders(): array
    {
        $credentials = json_decode((string) ($this->config['credentials'] ?? '{}'), true) ?: [];
        return ProviderAuthService::headers($credentials, 'api_secret');
    }

    private function mappedValue(string $key, array $fallback): string
    {
        $mappings = json_decode((string) ($this->config['mappings'] ?? '{}'), true) ?: [];
        return (string) ($mappings[$key] ?? $fallback[$key] ?? $key);
    }

    private function vtpassRequestId(string $reference): string
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('Africa/Lagos'));
        $suffix = preg_replace('/[^A-Za-z0-9]/', '', $reference) ?: bin2hex(random_bytes(4));
        return $now->format('YmdHi') . $suffix;
    }

    private function electricityFallbackMappings(): array
    {
        return [
            'abuja' => 'abuja-electric',
            'eko' => 'eko-electric',
            'ikeja' => 'ikeja-electric',
            'ibadan' => 'ibadan-electric',
            'ibedc' => 'ibadan-electric',
            'kano' => 'kano-electric',
            'jos' => 'jos-electric',
            'kaduna' => 'kaduna-electric',
            'enugu' => 'enugu-electric',
            'portharcourt' => 'portharcourt-electric',
            'phed' => 'portharcourt-electric',
            'benin' => 'benin-electric',
            'aba' => 'aba-electric',
            'yola' => 'yola-electric',
        ];
    }

    private function messageFromBody(mixed $body, string $fallback): string
    {
        if (!is_array($body)) {
            return $fallback;
        }
        return (string) (
            $body['response_description']
            ?? $body['message']
            ?? $body['content']['errors']
            ?? $body['content']['transactions']['status']
            ?? $fallback
        );
    }

    private function pinsFromBody(array $body): array
    {
        $tokens = $body['tokens'] ?? [];
        if (!is_array($tokens)) {
            $tokens = [];
        }
        if (empty($tokens) && !empty($body['purchased_code'])) {
            preg_match_all('/\b\d{8,}\b/', (string) $body['purchased_code'], $matches);
            $tokens = $matches[0] ?? [];
        }

        return array_values(array_map(static fn (string $token): array => ['pin' => $token, 'serial' => ''], $tokens));
    }

    private function tokenFromBody(array $body): string
    {
        $value = (string) ($body['token'] ?? $body['Token'] ?? $body['purchased_code'] ?? '');
        if ($value === '') {
            return '';
        }
        if (preg_match('/(?:token\s*:?\s*)?([0-9\-]{8,})/i', $value, $match)) {
            return $match[1];
        }
        return $value;
    }
}
