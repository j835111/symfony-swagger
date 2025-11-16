<?php

declare(strict_types=1);

namespace SymfonySwagger\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration class for SymfonySwaggerBundle.
 *
 * Defines the configuration structure for the bundle.
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('symfony_swagger');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('info')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('title')->defaultValue('API Documentation')->end()
                        ->scalarNode('description')->defaultValue('')->end()
                        ->scalarNode('version')->defaultValue('1.0.0')->end()
                    ->end()
                ->end()
                ->arrayNode('servers')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('url')->isRequired()->end()
                            ->scalarNode('description')->defaultValue('')->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('output_path')
                    ->defaultValue('%kernel.project_dir%/public/swagger.json')
                ->end()
                ->booleanNode('enabled')->defaultTrue()->end()
                ->enumNode('generation_mode')
                    ->values(['auto', 'runtime', 'static'])
                    ->defaultValue('runtime')
                ->end()
                ->arrayNode('cache')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->integerNode('ttl')->defaultValue(3600)->end()
                    ->end()
                ->end()
                ->arrayNode('analysis')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('max_depth')->defaultValue(5)->end()
                        ->booleanNode('include_internal_routes')->defaultFalse()->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
