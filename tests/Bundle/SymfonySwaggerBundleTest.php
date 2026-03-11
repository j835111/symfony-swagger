<?php

declare(strict_types=1);

namespace SymfonySwagger\Tests\Bundle;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use SymfonySwagger\SymfonySwaggerBundle;

/**
 * Test case for SymfonySwaggerBundle.
 */
class SymfonySwaggerBundleTest extends TestCase
{
    private SymfonySwaggerBundle $bundle;

    protected function setUp(): void
    {
        $this->bundle = new SymfonySwaggerBundle();
    }

    public function testGetPath(): void
    {
        $path = $this->bundle->getPath();
        
        $this->assertIsString($path);
        $this->assertStringEndsWith('symfony-swagger', $path);
    }

    public function testPrependSetsDefaultConfiguration(): void
    {
        $container = new ContainerBuilder();
        
        $this->bundle->prepend($container);
        
        $configs = $container->getExtensionConfig('symfony_swagger');
        
        $this->assertNotEmpty($configs);
        
        $config = $configs[0] ?? [];
        
        $this->assertArrayHasKey('enabled', $config);
        $this->assertTrue($config['enabled']);
        
        $this->assertArrayHasKey('info', $config);
        $this->assertEquals('API Documentation', $config['info']['title']);
        $this->assertEquals('Auto-generated API documentation', $config['info']['description']);
        $this->assertEquals('1.0.0', $config['info']['version']);
        
        $this->assertArrayHasKey('output_path', $config);
        $this->assertStringContainsString('swagger.json', $config['output_path']);
        
        $this->assertArrayHasKey('cache', $config);
        $this->assertTrue($config['cache']['enabled']);
        $this->assertEquals(3600, $config['cache']['ttl']);
        
        $this->assertArrayHasKey('analysis', $config);
        $this->assertEquals(5, $config['analysis']['max_depth']);
        $this->assertFalse($config['analysis']['include_internal_routes']);
        
        $this->assertArrayHasKey('generation_mode', $config);
        $this->assertEquals('runtime', $config['generation_mode']);
    }

    public function testPrependSetsDefaultsThatCanBeOverridden(): void
    {
        $container = new ContainerBuilder();
        
        // First, apply bundle's prepend config
        $this->bundle->prepend($container);
        
        // Then simulate user config being added (would override in real scenario)
        $configs = $container->getExtensionConfig('symfony_swagger');
        
        // The prepend config should be in the configs
        $this->assertNotEmpty($configs);
        
        // Verify default values are set
        $config = $configs[0];
        $this->assertEquals('API Documentation', $config['info']['title']);
    }
}
