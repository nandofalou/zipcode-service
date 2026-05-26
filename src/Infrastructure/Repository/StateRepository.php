<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Support\Normalizer;
use PDO;

final class StateRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByAbbr(int $countryId, string $abbr): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM state WHERE country_id = :country_id AND abbr = :abbr LIMIT 1'
        );
        $stmt->execute([
            'country_id' => $countryId,
            'abbr' => Normalizer::stateAbbr($abbr),
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findOrCreateByAbbr(int $countryId, string $abbr, ?string $name = null): array
    {
        $abbr = Normalizer::stateAbbr($abbr);
        $existing = $this->findByAbbr($countryId, $abbr);
        if ($existing !== null) {
            return $existing;
        }

        $stateName = Normalizer::text($name) ?? $abbr;

        $stmt = $this->pdo->prepare(
            'INSERT INTO state (country_id, name, abbr) VALUES (:country_id, :name, :abbr)'
        );
        $stmt->execute([
            'country_id' => $countryId,
            'name' => $stateName,
            'abbr' => $abbr,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        return [
            'id' => $id,
            'country_id' => $countryId,
            'name' => $stateName,
            'abbr' => $abbr,
            'ibge_code' => null,
        ];
    }
}
