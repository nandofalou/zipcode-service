<?php

declare(strict_types=1);

use App\Application\Import\IbgeLocalidadesImporter;
use App\Application\CepLookupService;
use App\Application\GetCepAction;
use App\Application\InstallAction;
use App\Application\InstallService;
use App\Application\ReverseGeocodeAction;
use App\Application\ReverseGeocodeService;
use App\Application\ServiceAccount\CreateServiceAccountAction;
use App\Application\ServiceAccount\DeleteServiceAccountAction;
use App\Application\ServiceAccount\ListServiceAccountsAction;
use App\Application\ServiceAccount\UpdateServiceAccountAction;
use App\Application\Zipcode\DeleteZipcodeAction;
use App\Application\Zipcode\ListZipcodesAction;
use App\Infrastructure\Database\PdoFactory;
use App\Infrastructure\Geocode\NominatimAddressMapper;
use App\Infrastructure\Geocode\NominatimClient;
use App\Infrastructure\Ibge\IbgeLocalidadesClient;
use App\Infrastructure\Provider\ApiCepProvider;
use App\Infrastructure\Provider\AwesomeApiProvider;
use App\Infrastructure\Provider\BrasilApiV1Provider;
use App\Infrastructure\Provider\BrasilApiV2Provider;
use App\Infrastructure\Provider\OpenCepProvider;
use App\Infrastructure\Provider\ViaCepProvider;
use App\Infrastructure\Provider\ZipcodeProviderChain;
use App\Infrastructure\Repository\CityRepository;
use App\Infrastructure\Repository\CountryRepository;
use App\Infrastructure\Repository\ServiceAccountRepository;
use App\Infrastructure\Repository\StateRepository;
use App\Infrastructure\Repository\ZipcodeRepository;
use App\Middleware\MasterAuthMiddleware;
use App\Middleware\ServiceAuthMiddleware;
use GuzzleHttp\Client;
use Psr\Container\ContainerInterface;
use function DI\create;
use function DI\factory;
use function DI\get;

$settings = require __DIR__ . '/settings.php';

return [
    'settings' => $settings,

    \PDO::class => factory(function () use ($settings): \PDO {
        return PdoFactory::create($settings['db_path']);
    }),

    Client::class => create()->constructor([
        'timeout' => 5,
        'connect_timeout' => 3,
        'http_errors' => false,
    ]),

    'nominatim.http' => factory(function () use ($settings): Client {
        return new Client([
            'timeout' => 5,
            'connect_timeout' => 3,
            'http_errors' => false,
            'headers' => [
                'User-Agent' => $settings['nominatim_user_agent'],
            ],
        ]);
    }),

    'ibge.http' => factory(function (): Client {
        return new Client([
            'timeout' => 30,
            'connect_timeout' => 5,
            'http_errors' => false,
        ]);
    }),

    NominatimClient::class => create()->constructor(
        get('nominatim.http'),
        $settings['nominatim_base_url'],
    ),
    NominatimAddressMapper::class => create(),

    IbgeLocalidadesClient::class => create()->constructor(
        get('ibge.http'),
        $settings['ibge_base_url'],
    ),

    CountryRepository::class => create()->constructor(get(\PDO::class)),
    StateRepository::class => create()->constructor(get(\PDO::class)),
    CityRepository::class => create()->constructor(get(\PDO::class)),
    ZipcodeRepository::class => create()->constructor(get(\PDO::class)),
    ServiceAccountRepository::class => create()->constructor(get(\PDO::class)),

    ZipcodeProviderChain::class => factory(function (ContainerInterface $c): ZipcodeProviderChain {
        $http = $c->get(Client::class);

        return new ZipcodeProviderChain([
            new ViaCepProvider($http),
            new AwesomeApiProvider($http),
            new BrasilApiV2Provider($http),
            new BrasilApiV1Provider($http),
            new OpenCepProvider($http),
            new ApiCepProvider($http),
        ]);
    }),

    InstallService::class => create()->constructor(
        get(\PDO::class),
        get(CountryRepository::class),
        get(ServiceAccountRepository::class),
        dirname(__DIR__) . '/database/schema.sql',
        $settings['default_country'],
    ),

    CepLookupService::class => create()->constructor(
        get(ZipcodeRepository::class),
        get(CountryRepository::class),
        get(StateRepository::class),
        get(CityRepository::class),
        get(ZipcodeProviderChain::class),
        $settings['default_country'],
    ),

    ReverseGeocodeService::class => create()->constructor(
        get(NominatimClient::class),
        get(NominatimAddressMapper::class),
        get(CepLookupService::class),
    ),

    IbgeLocalidadesImporter::class => create()->constructor(
        get(\PDO::class),
        get(IbgeLocalidadesClient::class),
        get(CountryRepository::class),
        get(StateRepository::class),
        get(CityRepository::class),
        $settings['default_country'],
    ),

    ServiceAuthMiddleware::class => create()->constructor(get(ServiceAccountRepository::class)),
    MasterAuthMiddleware::class => create(),

    InstallAction::class => create()->constructor(
        get(InstallService::class),
        $settings['install_enabled'],
    ),
    GetCepAction::class => create()->constructor(get(CepLookupService::class)),
    ReverseGeocodeAction::class => create()->constructor(get(ReverseGeocodeService::class)),
    ListServiceAccountsAction::class => create()->constructor(get(ServiceAccountRepository::class)),
    CreateServiceAccountAction::class => create()->constructor(get(ServiceAccountRepository::class)),
    UpdateServiceAccountAction::class => create()->constructor(get(ServiceAccountRepository::class)),
    DeleteServiceAccountAction::class => create()->constructor(get(ServiceAccountRepository::class)),
    ListZipcodesAction::class => create()->constructor(get(ZipcodeRepository::class)),
    DeleteZipcodeAction::class => create()->constructor(get(ZipcodeRepository::class)),
];
