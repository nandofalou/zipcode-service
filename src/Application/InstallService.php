<?php

declare(strict_types=1);

namespace App\Application;

use App\Infrastructure\Repository\CountryRepository;
use App\Infrastructure\Repository\ServiceAccountRepository;
use PDO;
use RuntimeException;

final class InstallService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly CountryRepository $countryRepository,
        private readonly ServiceAccountRepository $serviceAccountRepository,
        private readonly string $schemaPath,
        private readonly array $defaultCountry,
    ) {
    }

    public function install(): array
    {
        if (!file_exists($this->schemaPath)) {
            throw new RuntimeException('Arquivo de schema não encontrado.');
        }

        $schema = file_get_contents($this->schemaPath);
        if ($schema === false) {
            throw new RuntimeException('Não foi possível ler o schema.');
        }

        $this->pdo->exec($schema);
        $this->countryRepository->findOrCreateDefault($this->defaultCountry);

        if ($this->serviceAccountRepository->adminExists()) {
            return [
                'status' => true,
                'message' => 'Banco já instalado. Conta admin já existe.',
                'service_name' => 'admin',
                'service_token' => null,
            ];
        }

        $token = bin2hex(random_bytes(32));
        $account = $this->serviceAccountRepository->create('admin', $token, true);

        return [
            'status' => true,
            'message' => 'Instalação concluída.',
            'service_name' => $account['service_name'] ?? 'admin',
            'service_token' => $token,
            'is_master' => true,
        ];
    }
}
