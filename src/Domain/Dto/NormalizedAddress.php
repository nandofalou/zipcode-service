<?php

declare(strict_types=1);

namespace App\Domain\Dto;

final readonly class NormalizedAddress
{
    public function __construct(
        public string $zipcode,
        public ?string $street,
        public ?string $neighborhood,
        public string $stateAbbr,
        public string $cityName,
        public ?int $ibgeCode,
        public ?string $lat,
        public ?string $lng,
        public string $providerSlug,
        public ?string $stateName = null,
    ) {
    }
}
