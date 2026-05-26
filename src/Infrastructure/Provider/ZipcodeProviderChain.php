<?php

declare(strict_types=1);

namespace App\Infrastructure\Provider;

use App\Domain\Dto\NormalizedAddress;
use App\Domain\Provider\ZipcodeProviderInterface;

final class ZipcodeProviderChain
{
    /** @param ZipcodeProviderInterface[] $providers */
    public function __construct(private readonly array $providers)
    {
    }

    public function fetch(string $digitsOnly): ?NormalizedAddress
    {
        foreach ($this->providers as $provider) {
            $result = $provider->fetch($digitsOnly);
            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }
}
