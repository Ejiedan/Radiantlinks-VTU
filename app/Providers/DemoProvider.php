<?php

declare(strict_types=1);

namespace App\Providers;

use PDO;

final class DemoProvider implements ProviderInterface
{
    public function __construct(private PDO $db)
    {
    }

    public function purchase(string $service, array $payload, string $reference): array
    {
        $pinServices = ['exam_epin', 'data_pin', 'airtime_pin', 'recharge_card'];
        $details = [
            'provider_reference' => 'DEMO-' . $reference,
            'service' => $service,
        ];

        if (in_array($service, $pinServices, true)) {
            $qty = max(1, (int) ($payload['quantity'] ?? 1));
            $pins = [];
            for ($i = 0; $i < $qty; $i++) {
                $pins[] = [
                    'pin' => (string) random_int(100000000000, 999999999999),
                    'serial' => strtoupper(bin2hex(random_bytes(4))),
                ];
            }
            $details['pins'] = $pins;
        }

        if ($service === 'electricity') {
            $details['token'] = implode('-', [
                (string) random_int(1000, 9999),
                (string) random_int(1000, 9999),
                (string) random_int(1000, 9999),
                (string) random_int(1000, 9999),
                (string) random_int(1000, 9999),
            ]);
            $details['units'] = random_int(20, 90) . ' kWh';
        }

        return [
            'status' => 'success',
            'message' => 'Demo provider fulfilled transaction.',
            'details' => $details,
        ];
    }
}
