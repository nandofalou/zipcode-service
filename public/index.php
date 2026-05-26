<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(__DIR__ . '/../config/container.php');
$container = $containerBuilder->build();

AppFactory::setContainer($container);
$app = AppFactory::create();

$settings = $container->get('settings');
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(
    $settings['displayErrorDetails'],
    true,
    true
);

(require __DIR__ . '/../config/routes.php')($app);

$app->run();
