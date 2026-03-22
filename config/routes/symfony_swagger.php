<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use SymfonySwagger\Controller\SwaggerDocController;

return static function (RoutingConfigurator $routes): void {
    $routes->add('symfony_swagger_doc', '/api/docs.json')
        ->controller(SwaggerDocController::class.'::documentation')
        ->methods(['GET']);

    $routes->add('symfony_swagger_ui', '/api/docs')
        ->controller(SwaggerDocController::class.'::swaggerUi')
        ->methods(['GET']);

    $routes->add('symfony_swagger_scalar', '/api/docs/scalar')
        ->controller(SwaggerDocController::class.'::scalar')
        ->methods(['GET']);
};
