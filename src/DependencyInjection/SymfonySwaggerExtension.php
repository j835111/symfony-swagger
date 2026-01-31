<?php

declare(strict_types=1);

namespace SymfonySwagger\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * Extension class for SymfonySwaggerBundle.
 *
 * Handles loading and processing of bundle configuration.
 */
class SymfonySwaggerExtension extends Extension
{
    /**
     * Loads the bundle configuration.
     *
     * @param array<int, array<string, mixed>> $configs
     *
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Store configuration as parameters
        $container->setParameter('symfony_swagger.config', $config);
        $container->setParameter('symfony_swagger.enabled', $config['enabled']);
        $container->setParameter('symfony_swagger.output_path', $config['output_path']);

        // Load services
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.php');
    }
}
