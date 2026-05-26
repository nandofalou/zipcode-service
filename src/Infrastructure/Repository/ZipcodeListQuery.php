<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

final readonly class ZipcodeListQuery
{
    public function __construct(
        public ?string $city = null,
        public ?string $neighborhood = null,
        public ?string $stateAbbr = null,
        public ?string $zipcodePartial = null,
        public int $page = 1,
        public int $perPage = 20,
        public string $sortBy = 'created_at',
        public string $sortOrder = 'desc',
    ) {
    }
}
