<?php

declare(strict_types=1);

namespace App\Application\ServiceAccount;

use App\Infrastructure\Repository\ServiceAccountRepository;
use App\Support\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class CreateServiceAccountAction
{
    public function __construct(private readonly ServiceAccountRepository $repository)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $serviceName = trim((string) ($body['service_name'] ?? ''));

        if ($serviceName === '') {
            return JsonResponse::encode($response, [
                'status' => false,
                'message' => 'service_name é obrigatório.',
            ]);
        }

        try {
            $token = bin2hex(random_bytes(32));
            $account = $this->repository->create($serviceName, $token, false);
        } catch (\PDOException) {
            return JsonResponse::encode($response, [
                'status' => false,
                'message' => 'Não foi possível criar a conta (nome ou token duplicado).',
            ]);
        }

        return JsonResponse::encode($response, [
            'status' => true,
            'message' => 'Conta criada.',
            'data' => [
                'id' => (int) $account['id'],
                'service_name' => $account['service_name'],
                'service_token' => $token,
                'is_active' => (int) $account['is_active'],
                'is_master' => (int) $account['is_master'],
                'created_at' => $account['created_at'],
            ],
        ], 201);
    }
}
