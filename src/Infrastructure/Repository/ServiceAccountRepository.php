<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use DateTimeImmutable;
use PDO;

final class ServiceAccountRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findActiveByCredentials(string $serviceName, string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM service_account
             WHERE service_name = :service_name AND service_token = :token AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute([
            'service_name' => $serviceName,
            'token' => $token,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM service_account WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function listAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, service_name, is_active, is_master, created_at FROM service_account ORDER BY id'
        );

        return $stmt->fetchAll();
    }

    public function create(string $serviceName, string $token, bool $isMaster = false): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO service_account (service_name, service_token, is_active, is_master, created_at)
             VALUES (:service_name, :service_token, 1, :is_master, :created_at)'
        );
        $stmt->execute([
            'service_name' => $serviceName,
            'service_token' => $token,
            'is_master' => $isMaster ? 1 : 0,
            'created_at' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
        ]);

        $id = (int) $this->pdo->lastInsertId();

        return $this->findById($id) ?? [];
    }

    public function update(int $id, ?bool $isActive = null, ?string $newToken = null): ?array
    {
        $account = $this->findById($id);
        if ($account === null) {
            return null;
        }

        $isActiveValue = $isActive !== null ? ($isActive ? 1 : 0) : (int) $account['is_active'];
        $token = $newToken ?? $account['service_token'];

        $stmt = $this->pdo->prepare(
            'UPDATE service_account SET is_active = :is_active, service_token = :token WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'is_active' => $isActiveValue,
            'token' => $token,
        ]);

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $account = $this->findById($id);
        if ($account === null) {
            return false;
        }

        if ((int) $account['is_master'] === 1) {
            return false;
        }

        $stmt = $this->pdo->prepare('DELETE FROM service_account WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function adminExists(): bool
    {
        $stmt = $this->pdo->query("SELECT 1 FROM service_account WHERE service_name = 'admin' LIMIT 1");

        return (bool) $stmt->fetchColumn();
    }
}
