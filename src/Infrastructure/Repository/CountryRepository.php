<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use PDO;

final class CountryRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByAlpha2(string $code): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM country WHERE alphacode2 = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findOrCreateDefault(array $country): array
    {
        $existing = $this->findByAlpha2($country['alphacode2']);
        if ($existing !== null) {
            return $existing;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO country (name, alphacode2, alphacode3, numcode) VALUES (:name, :a2, :a3, :num)'
        );
        $stmt->execute([
            'name' => $country['name'],
            'a2' => $country['alphacode2'],
            'a3' => $country['alphacode3'],
            'num' => $country['numcode'],
        ]);

        $id = (int) $this->pdo->lastInsertId();

        return [
            'id' => $id,
            'name' => $country['name'],
            'alphacode2' => $country['alphacode2'],
            'alphacode3' => $country['alphacode3'],
            'numcode' => $country['numcode'],
        ];
    }
}
