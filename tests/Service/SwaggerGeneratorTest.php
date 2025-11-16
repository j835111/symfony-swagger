<?php

declare(strict_types=1);

namespace SymfonySwagger\Tests\Service;

use PHPUnit\Framework\TestCase;
use SymfonySwagger\Service\SwaggerGenerator;

/**
 * Test case for SwaggerGenerator service.
 */
class SwaggerGeneratorTest extends TestCase
{
    private SwaggerGenerator $generator;

    protected function setUp(): void
    {
        $config = [
            'info' => [
                'title' => 'Test API',
                'description' => 'Test Description',
                'version' => '1.0.0',
            ],
            'servers' => [
                [
                    'url' => 'https://api.test.com',
                    'description' => 'Test Server',
                ],
            ],
            'enabled' => true,
            'output_path' => '/tmp/swagger.json',
        ];

        $this->generator = new SwaggerGenerator($config);
    }

    public function testGenerate(): void
    {
        $result = $this->generator->generate();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('openapi', $result);
        $this->assertArrayHasKey('info', $result);
        $this->assertArrayHasKey('servers', $result);
        $this->assertArrayHasKey('paths', $result);
        $this->assertArrayHasKey('components', $result);
    }

    public function testGenerateHasCorrectOpenApiVersion(): void
    {
        $result = $this->generator->generate();

        // Updated to OpenAPI 3.1.0
        $this->assertEquals('3.1.0', $result['openapi']);
    }

    public function testGenerateHasCorrectInfo(): void
    {
        $result = $this->generator->generate();

        $this->assertEquals('Test API', $result['info']['title']);
        $this->assertEquals('Test Description', $result['info']['description']);
        $this->assertEquals('1.0.0', $result['info']['version']);
    }

    public function testGenerateHasCorrectServers(): void
    {
        $result = $this->generator->generate();

        $this->assertCount(1, $result['servers']);
        $this->assertEquals('https://api.test.com', $result['servers'][0]['url']);
        $this->assertEquals('Test Server', $result['servers'][0]['description']);
    }

    public function testGetConfig(): void
    {
        $config = $this->generator->getConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('info', $config);
        $this->assertArrayHasKey('servers', $config);
        $this->assertArrayHasKey('enabled', $config);
        $this->assertTrue($config['enabled']);
    }
}
