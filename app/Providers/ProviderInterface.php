<?php

declare(strict_types=1);

namespace App\Providers;

interface ProviderInterface
{
    public function purchase(string $service, array $payload, string $reference): array;
}

