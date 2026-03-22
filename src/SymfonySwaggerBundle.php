<?php

declare(strict_types=1);

namespace SymfonySwagger;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * SymfonySwaggerBundle.
 *
 * Main bundle class for Symfony Swagger/OpenAPI integration.
 *
 * Features:
 * - Auto-loads default configuration (no manual config file needed)
 * - Ships a route resource for the built-in documentation endpoints
 */
class SymfonySwaggerBundle extends Bundle
{
    /**
     * Returns the bundle path.
     */
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    /**
     * Prepend default configuration.
     *
     * This allows the bundle to work without requiring users to manually
     * create the config/packages/symfony_swagger.yaml file.
     */
    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('symfony_swagger', [
            'enabled' => true,
            'info' => [
                'title' => 'API Documentation',
                'description' => 'Auto-generated API documentation',
                'version' => '1.0.0',
            ],
            'output_path' => '%kernel.project_dir%/public/swagger.json',
            'cache' => [
                'enabled' => true,
                'ttl' => 3600,
            ],
            'analysis' => [
                'max_depth' => 5,
                'include_internal_routes' => false,
            ],
            'generation_mode' => 'runtime',
        ]);
    }
}
