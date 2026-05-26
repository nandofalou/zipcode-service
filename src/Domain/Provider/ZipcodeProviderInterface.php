<?php

declare(strict_types=1);

namespace App\Domain\Provider;

use App\Domain\Dto\NormalizedAddress;

interface ZipcodeProviderInterface
{
    public function getSlug(): string;

    public function fetch(string $digitsOnly): ?NormalizedAddress;
}
