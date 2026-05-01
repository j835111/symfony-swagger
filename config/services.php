<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use SymfonySwagger\Analyzer\AttributeReader;
use SymfonySwagger\Analyzer\TypeAnalyzer;
use SymfonySwagger\Routing\AutoRouteLoader;
use SymfonySwagger\Service\Describer\OperationDescriber;
use SymfonySwagger\Service\Describer\RouteDescriber;
use SymfonySwagger\Service\Describer\SchemaDescriber;
use SymfonySwagger\Service\OpenApiGenerator;
use SymfonySwagger\Service\Registry\SchemaRegistry;
use SymfonySwagger\Service\SwaggerGenerator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
    ;

    // Analyzer services
    $services->set(AttributeReader::class);

    $services->set(TypeAnalyzer::class)
        ->arg('$maxDepth', param('symfony_swagger.analysis.max_depth'))
    ;

    // Registry services
    $services->set(SchemaRegistry::class);

    // Describer services
    $services->set(RouteDescriber::class);

    $services->set(SchemaDescriber::class)
        ->args([
            service(TypeAnalyzer::class),
            service(SchemaRegistry::class),
        ])
    ;

    $services->set(OperationDescriber::class)
        ->args([
            service(AttributeReader::class),
            service(TypeAnalyzer::class),
            service(SchemaDescriber::class),
            param('symfony_swagger.config'),
        ])
    ;

    // Main OpenAPI Generator
    $services->set(OpenApiGenerator::class)
        ->public()
        ->args([
            service('router'),
            service(RouteDescriber::class),
            service(OperationDescriber::class),
            service(SchemaRegistry::class),
            service('cache.app')->nullOnInvalid(),
            param('symfony_swagger.config'),
            service('logger')->nullOnInvalid(),
        ])
    ;

    $services->set(AutoRouteLoader::class)
        ->decorate('routing.loader')
        ->args([
            service('.inner'),
            param('symfony_swagger.enabled'),
        ])
    ;

    // Legacy Swagger Generator (backward compatibility)
    $services->set(SwaggerGenerator::class)
        ->public()
        ->args([
            param('symfony_swagger.config'),
            service(OpenApiGenerator::class)->nullOnInvalid(),
        ])
    ;

    // Auto-register all commands in the Command directory
    $commandDir = __DIR__.'/../src/Command';
    if (is_dir($commandDir)) {
        $services->load('SymfonySwagger\\Command\\', $commandDir)
        ->tag('console.command')
    ;
    }

    // Auto-register all controllers in the Controller directory
    $controllerDir = __DIR__.'/../src/Controller';
    if (is_dir($controllerDir)) {
        $services->load('SymfonySwagger\\Controller\\', $controllerDir);
    }
};
