<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use SymfonySwagger\Analyzer\AttributeReader;
use SymfonySwagger\Analyzer\TypeAnalyzer;
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
        ->arg('$maxDepth', param('symfony_swagger.config')['analysis']['max_depth'] ?? 5)
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

    // Legacy Swagger Generator (backward compatibility)
    $services->set(SwaggerGenerator::class)
        ->public()
        ->args([
            param('symfony_swagger.config'),
            service(OpenApiGenerator::class)->nullOnInvalid(),
        ])
    ;

    // Auto-register all commands in the Command directory
    $services->load('SymfonySwagger\\Command\\', __DIR__.'/../src/Command')
        ->tag('console.command')
    ;
};
