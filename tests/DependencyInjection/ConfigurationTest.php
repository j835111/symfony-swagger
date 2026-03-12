<?php

declare(strict_types=1);

namespace SymfonySwagger\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use SymfonySwagger\DependencyInjection\Configuration;

/**
 * Test case for Configuration.
 */
class ConfigurationTest extends TestCase
{
    private Configuration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new Configuration();
    }

    public function testGetConfigTreeBuilder(): void
    {
        $treeBuilder = $this->configuration->getConfigTreeBuilder();

        $this->assertNotNull($treeBuilder);
    }

    public function testDefaultValues(): void
    {
        $processor = new Processor();
        $config = $processor->processConfiguration($this->configuration, []);

        $this->assertTrue($config['enabled']);
        $this->assertEquals('API Documentation', $config['info']['title']);
        $this->assertEquals('', $config['info']['description']);
        $this->assertEquals('1.0.0', $config['info']['version']);
        $this->assertEquals('%kernel.project_dir%/public/swagger.json', $config['output_path']);
        $this->assertEquals('runtime', $config['generation_mode']);
    }

    public function testCacheDefaults(): void
    {
        $processor = new Processor();
        $config = $processor->processConfiguration($this->configuration, []);

        $this->assertTrue($config['cache']['enabled']);
        $this->assertEquals(3600, $config['cache']['ttl']);
    }

    public function testAnalysisDefaults(): void
    {
        $processor = new Processor();
        $config = $processor->processConfiguration($this->configuration, []);

        $this->assertEquals(5, $config['analysis']['max_depth']);
        $this->assertFalse($config['analysis']['include_internal_routes']);
    }

    public function testServersConfiguration(): void
    {
        $input = [
            'servers' => [
                ['url' => 'https://api.test.com', 'description' => 'Test'],
            ],
        ];

        $processor = new Processor();
        $config = $processor->processConfiguration($this->configuration, [$input]);

        $this->assertCount(1, $config['servers']);
        $this->assertEquals('https://api.test.com', $config['servers'][0]['url']);
    }

    public function testInfoConfiguration(): void
    {
        $input = [
            'info' => [
                'title' => 'Custom Title',
                'description' => 'Custom Description',
                'version' => '2.0.0',
            ],
        ];

        $processor = new Processor();
        $config = $processor->processConfiguration($this->configuration, [$input]);

        $this->assertEquals('Custom Title', $config['info']['title']);
        $this->assertEquals('Custom Description', $config['info']['description']);
        $this->assertEquals('2.0.0', $config['info']['version']);
    }

    public function testGenerationModeValues(): void
    {
        $input = ['generation_mode' => 'static'];

        $processor = new Processor();
        $config = $processor->processConfiguration($this->configuration, [$input]);

        $this->assertEquals('static', $config['generation_mode']);
    }
}
