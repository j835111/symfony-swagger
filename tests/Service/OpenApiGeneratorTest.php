<?php

declare(strict_types=1);

namespace SymfonySwagger\Tests\Service;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use SymfonySwagger\Service\OpenApiGenerator;
use SymfonySwagger\Service\Describer\RouteDescriber;
use SymfonySwagger\Service\Describer\OperationDescriber;
use SymfonySwagger\Service\Registry\SchemaRegistry;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Psr\Log\LoggerInterface;

/**
 * Test case for OpenApiGenerator service.
 */
class OpenApiGeneratorTest extends TestCase
{
    private OpenApiGenerator $generator;
    /** @var RouterInterface&MockObject */
    private $router;
    /** @var RouteDescriber&MockObject */
    private $routeDescriber;
    /** @var OperationDescriber&MockObject */
    private $operationDescriber;
    /** @var SchemaRegistry&MockObject */
    private $schemaRegistry;

    protected function setUp(): void
    {
        $this->router = $this->createMock(RouterInterface::class);
        $this->routeDescriber = $this->createMock(RouteDescriber::class);
        $this->operationDescriber = $this->createMock(OperationDescriber::class);
        $this->schemaRegistry = $this->createMock(SchemaRegistry::class);

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

        $this->generator = new OpenApiGenerator(
            $this->router,
            $this->routeDescriber,
            $this->operationDescriber,
            $this->schemaRegistry,
            null,
            $config,
            null
        );
    }

    public function testGenerate(): void
    {
        $this->schemaRegistry->method('getSchemas')->willReturn([]);

        $result = $this->generator->generate();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('openapi', $result);
        $this->assertArrayHasKey('info', $result);
        $this->assertArrayHasKey('servers', $result);
        $this->assertArrayHasKey('paths', $result);
    }

    public function testGenerateHasCorrectOpenApiVersion(): void
    {
        $this->schemaRegistry->method('getSchemas')->willReturn([]);
        $result = $this->generator->generate();

        $this->assertEquals('3.1.0', $result['openapi']);
    }

    public function testGenerateHasCorrectInfo(): void
    {
        $this->schemaRegistry->method('getSchemas')->willReturn([]);
        $result = $this->generator->generate();

        $this->assertEquals('Test API', $result['info']['title']);
        $this->assertEquals('Test Description', $result['info']['description']);
        $this->assertEquals('1.0.0', $result['info']['version']);
    }

    public function testGenerateHasCorrectServers(): void
    {
        $this->schemaRegistry->method('getSchemas')->willReturn([]);
        $result = $this->generator->generate();

        $this->assertCount(1, $result['servers']);
        $this->assertEquals('https://api.test.com', $result['servers'][0]['url']);
        $this->assertEquals('Test Server', $result['servers'][0]['description']);
    }
}
