<?php

declare(strict_types=1);

namespace App\Application\ServiceAccount;

use App\Infrastructure\Repository\ServiceAccountRepository;
use App\Support\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class UpdateServiceAccountAction
{
    public function __construct(private readonly ServiceAccountRepository $repository)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) {
            return JsonResponse::encode($response, [
                'status' => false,
                'message' => 'ID inválido.',
            ]);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $isActive = null;
        if (array_key_exists('is_active', $body)) {
            $isActive = filter_var($body['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        $rotateToken = filter_var($body['rotate_token'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $newToken = $rotateToken ? bin2hex(random_bytes(32)) : null;

        $account = $this->repository->update($id, $isActive, $newToken);
        if ($account === null) {
            return JsonResponse::encode($response, [
                'status' => false,
                'message' => 'Conta não encontrada.',
            ]);
        }

        $data = [
            'id' => (int) $account['id'],
            'service_name' => $account['service_name'],
            'is_active' => (int) $account['is_active'],
            'is_master' => (int) $account['is_master'],
            'created_at' => $account['created_at'],
        ];

        if ($newToken !== null) {
            $data['service_token'] = $newToken;
        }

        return JsonResponse::encode($response, [
            'status' => true,
            'message' => 'Conta atualizada.',
            'data' => $data,
        ]);
    }
}
