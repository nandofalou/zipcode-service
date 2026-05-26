<?php

declare(strict_types=1);

namespace App\Application;

use App\Support\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class InstallAction
{
    public function __construct(
        private readonly InstallService $installService,
        private readonly bool $installEnabled,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->installEnabled) {
            return JsonResponse::encode($response, [
                'status' => false,
                'message' => 'Instalação desabilitada.',
            ], 403);
        }

        try {
            $result = $this->installService->install();
        } catch (\Throwable $e) {
            return JsonResponse::encode($response, [
                'status' => false,
                'message' => 'Erro na instalação: ' . $e->getMessage(),
            ]);
        }

        return JsonResponse::encode($response, $result);
    }
}
