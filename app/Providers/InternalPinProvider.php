<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\InternalPinService;
use PDO;

final class InternalPinProvider implements ProviderInterface
{
    public function __construct(private PDO $db)
    {
    }

    public function purchase(string $service, array $payload, string $reference): array
    {
        $provider = strtolower(trim((string) ($payload['provider'] ?? '')));
        $value = trim((string) ($payload['plan_id'] ?? $payload['value'] ?? $payload['amount'] ?? ''));
        $type = trim((string) ($payload['service_type'] ?? 'local')) ?: 'local';
        $quantity = max(1, (int) ($payload['quantity'] ?? 1));

        if ($provider === '' || $value === '') {
            return ['status' => 'failed', 'message' => 'PIN provider and denomination/plan are required.', 'details' => []];
        }

        $pins = (new InternalPinService($this->db))->reserve($service, $provider, $value, $quantity, $type);
        if (empty($pins)) {
            return [
                'status' => 'failed',
                'message' => 'PIN is out of stock for the selected provider/value.',
                'details' => compact('service', 'provider', 'value', 'type', 'quantity'),
            ];
        }

        return [
            'status' => 'success',
            'message' => 'PIN purchase successful',
            'details' => [
                'provider_reference' => $reference,
                'pins' => $pins,
            ],
        ];
    }
}
