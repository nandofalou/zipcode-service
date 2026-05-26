<?php

declare(strict_types=1);

namespace App\Application\Zipcode;

use App\Infrastructure\Repository\ZipcodeRepository;
use App\Support\JsonResponse;
use App\Support\Normalizer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class DeleteZipcodeAction
{
    public function __construct(private readonly ZipcodeRepository $repository)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $cep = Normalizer::zipcode((string) ($args['cep'] ?? ''));
        if (strlen($cep) !== 8) {
            return JsonResponse::encode($response, [
                'status' => false,
                'message' => 'CEP inválido. Informe 8 dígitos.',
            ]);
        }

        if (!$this->repository->deleteByZipcode($cep)) {
            return JsonResponse::encode($response, [
                'status' => false,
                'message' => 'CEP não encontrado.',
            ]);
        }

        return JsonResponse::encode($response, [
            'status' => true,
            'message' => 'CEP excluído.',
            'zipcode' => $cep,
        ]);
    }
}
