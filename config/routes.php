<?php

declare(strict_types=1);

use App\Application\GetCepAction;
use App\Application\InstallAction;
use App\Application\ReverseGeocodeAction;
use App\Application\ServiceAccount\CreateServiceAccountAction;
use App\Application\ServiceAccount\DeleteServiceAccountAction;
use App\Application\ServiceAccount\ListServiceAccountsAction;
use App\Application\ServiceAccount\UpdateServiceAccountAction;
use App\Application\Zipcode\DeleteZipcodeAction;
use App\Application\Zipcode\ListZipcodesAction;
use App\Middleware\MasterAuthMiddleware;
use App\Middleware\ServiceAuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app): void {
    $app->get('/api/install', InstallAction::class);

    $app->group('/api', function (RouteCollectorProxy $group): void {
        $group->get('/getcep/{cep}', GetCepAction::class);
        $group->get('/reverse-geocode', ReverseGeocodeAction::class);
        $group->post('/reverse-geocode', ReverseGeocodeAction::class);

        $group->group('', function (RouteCollectorProxy $admin): void {
            $admin->get('/service-accounts', ListServiceAccountsAction::class);
            $admin->post('/service-accounts', CreateServiceAccountAction::class);
            $admin->put('/service-accounts/{id}', UpdateServiceAccountAction::class);
            $admin->delete('/service-accounts/{id}', DeleteServiceAccountAction::class);

            $admin->get('/zipcodes', ListZipcodesAction::class);
            $admin->delete('/zipcodes/{cep}', DeleteZipcodeAction::class);
        })->add(MasterAuthMiddleware::class);
    })->add(ServiceAuthMiddleware::class);
};
