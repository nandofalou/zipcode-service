<?php

declare(strict_types=1);

namespace App\Application\ServiceAccount;

use App\Infrastructure\Repository\ServiceAccountRepository;
use App\Support\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class DeleteServiceAccountAction
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

        if (!$this->repository->delete($id)) {
            return JsonResponse::encode($response, [
                'status' => false,
                'message' => 'Não foi possível excluir (conta inexistente ou é master).',
            ]);
        }

        return JsonResponse::encode($response, [
            'status' => true,
            'message' => 'Conta excluída.',
        ]);
    }
}
