<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\HttpClient;
use App\Services\ProviderAuthService;
use PDO;

final class VtupressPinProvider implements ProviderInterface
{
    public function __construct(private PDO $db, private array $config)
    {
    }

    public function purchase(string $service, array $payload, string $reference): array
    {
        $key = (string) $this->config['provider_key'];
        return match ($key) {
            'stepupconnect_exam' => $this->stepupconnectExam($payload, $reference),
            'telecomabode_data' => $this->telecomAbodeData($payload, $reference),
            'stepupconnect_recharge' => $this->stepupconnectRecharge($payload, $reference),
            'telecomabode_recharge' => $this->telecomAbodeRecharge($payload, $reference),
            default => [
                'status' => 'failed',
                'message' => 'Selected preset is not wired for direct customer purchases yet.',
                'details' => ['provider_key' => $key],
            ],
        };
    }

    private function stepupconnectExam(array $payload, string $reference): array
    {
        $credentials = $this->credentials();
        $mappings = $this->mappings();
        $exam = strtolower((string) ($payload['provider'] ?? ''));
        $examId = $mappings[$exam] ?? null;
        if (!$examId) {
            return ['status' => 'failed', 'message' => 'Exam mapping is missing.', 'details' => compact('exam')];
        }

        $response = (new HttpClient())->postJson((string) $this->config['base_url'], [
            'exam' => (int) $examId,
            'quantity' => max(1, (int) ($payload['quantity'] ?? 1)),
        ], ProviderAuthService::headers($credentials, 'token'));

        return $this->pinResponse($response, $reference);
    }

    private function telecomAbodeData(array $payload, string $reference): array
    {
        $credentials = $this->credentials();
        $mappings = $this->mappings();
        $network = strtolower((string) ($payload['provider'] ?? ''));
        $networkValue = strtolower((string) ($mappings[$network] ?? $network));
        $networkId = ['mtn' => 1, 'airtel' => 2, 'glo' => 3, '9mobile' => 4][$networkValue] ?? null;
        if (!$networkId) {
            return ['status' => 'failed', 'message' => 'Network mapping is missing.', 'details' => compact('network')];
        }

        $response = (new HttpClient())->postJson((string) $this->config['base_url'], [
            'network' => $networkId,
            'plan_type' => (int) ($payload['plan_id'] ?? 0),
            'quantity' => max(1, (int) ($payload['quantity'] ?? 1)),
            'card_name' => $credentials['name_on_card'] ?? 'Radiantlinks VTU',
        ], ProviderAuthService::headers($credentials, 'token'));

        return $this->pinResponse($response, $reference);
    }

    private function stepupconnectRecharge(array $payload, string $reference): array
    {
        $credentials = $this->credentials();
        $mappings = $this->mappings();
        $network = strtolower((string) ($payload['provider'] ?? ''));
        $networkId = $mappings[$network] ?? null;
        if (!$networkId) {
            return ['status' => 'failed', 'message' => 'Network mapping is missing.', 'details' => compact('network')];
        }

        $response = (new HttpClient())->postJson((string) $this->config['base_url'], [
            'network' => (int) $networkId,
            'quantity' => max(1, (int) ($payload['quantity'] ?? 1)),
            'amount' => (float) ($payload['amount'] ?? 0),
            'name_on_card' => $credentials['name_on_card'] ?? 'Radiantlinks VTU',
        ], ProviderAuthService::headers($credentials, 'token'));

        return $this->pinResponse($response, $reference);
    }

    private function telecomAbodeRecharge(array $payload, string $reference): array
    {
        $credentials = $this->credentials();
        $mappings = $this->mappings();
        $network = strtolower((string) ($payload['provider'] ?? ''));
        $networkValue = strtolower((string) ($mappings[$network] ?? $network));
        $networkId = ['mtn' => 1, 'airtel' => 2, 'glo' => 3, '9mobile' => 4][$networkValue] ?? null;
        if (!$networkId) {
            return ['status' => 'failed', 'message' => 'Network mapping is missing.', 'details' => compact('network')];
        }

        $planType = (int) ($payload['plan_id'] ?? 0);
        if ($planType <= 0) {
            $planType = $this->telecomAbodeRechargePlanId((float) ($payload['amount'] ?? 0), $network);
        }
        if ($planType <= 0) {
            return ['status' => 'failed', 'message' => 'Recharge card plan type is missing.', 'details' => compact('network')];
        }

        $response = (new HttpClient())->postJson((string) $this->config['base_url'], [
            'network' => $networkId,
            'plan_type' => $planType,
            'quantity' => max(1, (int) ($payload['quantity'] ?? 1)),
            'card_name' => $credentials['name_on_card'] ?? 'Radiantlinks VTU',
        ], ProviderAuthService::headers($credentials, 'token'));

        return $this->pinResponse($response, $reference);
    }

    private function pinResponse(array $response, string $reference): array
    {
        if (!$response['ok']) {
            return ['status' => 'failed', 'message' => 'Provider request failed', 'details' => $response];
        }

        $body = is_array($response['body']) ? $response['body'] : [];
        $status = strtolower((string) ($body['status'] ?? ''));
        if (!in_array($status, ['success', 'successful'], true)) {
            return [
                'status' => 'failed',
                'message' => (string) ($body['message'] ?? $body['response'] ?? $body['msg'] ?? 'Provider failed'),
                'details' => $body,
            ];
        }

        $pins = $this->extractPins($body);
        return [
            'status' => 'success',
            'message' => 'PIN purchase successful',
            'details' => [
                'provider_reference' => $body['reference'] ?? $reference,
                'raw' => $body,
                'pins' => $pins,
            ],
        ];
    }

    private function extractPins(array $body): array
    {
        if (!empty($body['pins']) && is_array($body['pins'])) {
            return $body['pins'];
        }

        $pinString = (string) ($body['pin'] ?? '');
        $serialString = (string) ($body['serial'] ?? '');
        if (preg_match('/Pin:\s*=>\s*(.*?)(?:\s*\|\||$)/i', $pinString, $matches)) {
            $pinString = $matches[1];
        }
        if (preg_match('/serial:\s*=>\s*(.*?)(?:\s*\|\||$)/i', $pinString, $matches) && $serialString === '') {
            $serialString = $matches[1];
        }

        $pins = array_map('trim', explode(',', $pinString));
        $serials = array_map('trim', explode(',', $serialString));
        $out = [];
        foreach ($pins as $index => $pin) {
            if ($pin === '' || strtolower($pin) === 'null') {
                continue;
            }
            $out[] = ['pin' => $pin, 'serial' => $serials[$index] ?? ''];
        }
        return $out;
    }

    private function telecomAbodeRechargePlanId(float $amount, string $network): int
    {
        $ids = [
            'mtn' => [100 => 2, 200 => 12, 500 => 13, 1000 => 14],
            'airtel' => [100 => 15, 200 => 16, 500 => 17, 1000 => 18],
            'glo' => [100 => 19, 200 => 20, 500 => 21, 1000 => 22],
            '9mobile' => [100 => 23, 200 => 24, 500 => 25, 1000 => 26],
        ];

        return (int) ($ids[strtolower($network)][(int) $amount] ?? 0);
    }

    private function credentials(): array
    {
        return json_decode((string) ($this->config['credentials'] ?? '{}'), true) ?: [];
    }

    private function mappings(): array
    {
        return json_decode((string) ($this->config['mappings'] ?? '{}'), true) ?: [];
    }
}
