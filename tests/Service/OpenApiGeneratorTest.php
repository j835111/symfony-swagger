<?php

declare(strict_types=1);

namespace SymfonySwagger\Tests\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use SymfonySwagger\Service\Describer\OperationDescriber;
use SymfonySwagger\Service\Describer\RouteDescriber;
use SymfonySwagger\Service\OpenApiGenerator;
use SymfonySwagger\Service\Registry\SchemaRegistry;

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
            null,
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

    public function testGenerateWithEmptyServers(): void
    {
        $config = [
            'info' => [
                'title' => 'Test API',
                'description' => '',
                'version' => '1.0.0',
            ],
            'servers' => [],
            'enabled' => true,
            'output_path' => '/tmp/swagger.json',
        ];

        $generator = new OpenApiGenerator(
            $this->router,
            $this->routeDescriber,
            $this->operationDescriber,
            $this->schemaRegistry,
            null,
            $config,
            null,
        );

        $this->schemaRegistry->method('getSchemas')->willReturn([]);
        $result = $generator->generate();

        $this->assertArrayHasKey('servers', $result);
        $this->assertEmpty($result['servers']);
    }

    public function testGenerateWithSchemas(): void
    {
        $schemas = [
            'User' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                ],
            ],
        ];

        $this->schemaRegistry->method('getSchemas')->willReturn($schemas);

        $result = $this->generator->generate();

        $this->assertArrayHasKey('components', $result);
        $this->assertArrayHasKey('schemas', $result['components']);
        $this->assertEquals($schemas, $result['components']['schemas']);
    }

    public function testGenerateWithDefaultInfoValues(): void
    {
        $config = [
            'info' => [],
            'servers' => [],
            'enabled' => true,
            'output_path' => '/tmp/swagger.json',
        ];

        $generator = new OpenApiGenerator(
            $this->router,
            $this->routeDescriber,
            $this->operationDescriber,
            $this->schemaRegistry,
            null,
            $config,
            null,
        );

        $this->schemaRegistry->method('getSchemas')->willReturn([]);
        $result = $generator->generate();

        $this->assertEquals('API Documentation', $result['info']['title']);
        $this->assertEquals('', $result['info']['description']);
        $this->assertEquals('1.0.0', $result['info']['version']);
    }

    public function testGenerateWithCache(): void
    {
        $cache = $this->createMock(CacheInterface::class);

        $config = [
            'info' => ['title' => 'Test', 'description' => '', 'version' => '1.0.0'],
            'servers' => [],
            'enabled' => true,
            'output_path' => '/tmp/swagger.json',
            'cache' => ['enabled' => true, 'ttl' => 3600],
        ];

        $generator = new OpenApiGenerator(
            $this->router,
            $this->routeDescriber,
            $this->operationDescriber,
            $this->schemaRegistry,
            $cache,
            $config,
            null,
        );

        $this->schemaRegistry->method('getSchemas')->willReturn([]);

        $cache->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->expects($this->once())
                    ->method('expiresAfter')
                    ->with(3600);

                return $callback($item);
            });

        $result = $generator->generate();

        $this->assertIsArray($result);
    }

    public function testGenerateWithCacheDisabled(): void
    {
        $cache = $this->createMock(CacheInterface::class);

        $config = [
            'info' => ['title' => 'Test', 'description' => '', 'version' => '1.0.0'],
            'servers' => [],
            'enabled' => true,
            'output_path' => '/tmp/swagger.json',
            'cache' => ['enabled' => false],
        ];

        $generator = new OpenApiGenerator(
            $this->router,
            $this->routeDescriber,
            $this->operationDescriber,
            $this->schemaRegistry,
            $cache,
            $config,
            null,
        );

        $this->schemaRegistry->method('getSchemas')->willReturn([]);
        $cache->expects($this->never())->method('get');

        $result = $generator->generate();

        $this->assertIsArray($result);
    }

    public function testL1CacheReturnsSameResult(): void
    {
        $this->schemaRegistry->method('getSchemas')->willReturn([]);

        $result1 = $this->generator->generate();
        $result2 = $this->generator->generate();

        // Second call should return cached result
        $this->assertEquals($result1, $result2);
        $this->assertSame($result1, $result2);
    }

    public function testClearCache(): void
    {
        $cache = $this->createMock(CacheInterface::class);

        $config = [
            'info' => ['title' => 'Test', 'description' => '', 'version' => '1.0.0'],
            'servers' => [],
            'enabled' => true,
            'output_path' => '/tmp/swagger.json',
            'cache' => ['enabled' => true],
        ];

        $generator = new OpenApiGenerator(
            $this->router,
            $this->routeDescriber,
            $this->operationDescriber,
            $this->schemaRegistry,
            $cache,
            $config,
            null,
        );

        $cache->expects($this->once())
            ->method('delete')
            ->willReturn(true);

        $generator->clearCache();
    }

    public function testGenerateWithPaths(): void
    {
        $route = new Route('/api/users', ['_controller' => 'TestController::index']);

        $this->router->method('getRouteCollection')->willReturn(new \Symfony\Component\Routing\RouteCollection());

        $routes = [
            'app_users' => [
                'route' => $route,
                'controller' => 'TestController',
                'method' => 'index',
                'reflection' => new \ReflectionMethod($this::class, 'testGenerateWithPaths'),
            ],
        ];

        $this->routeDescriber->expects($this->once())
            ->method('describe')
            ->willReturn($routes);

        $this->schemaRegistry->method('getSchemas')->willReturn([]);

        $operation = ['summary' => 'List users', 'operationId' => 'testGenerateWithPaths'];
        $this->operationDescriber->expects($this->once())
            ->method('describe')
            ->willReturn($operation);

        $result = $this->generator->generate();

        $this->assertArrayHasKey('paths', $result);
        $this->assertArrayHasKey('/api/users', $result['paths']);
    }

    public function testGenerateHandlesOperationDescriberError(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $config = [
            'info' => ['title' => 'Test', 'description' => '', 'version' => '1.0.0'],
            'servers' => [],
            'enabled' => true,
            'output_path' => '/tmp/swagger.json',
        ];

        $generator = new OpenApiGenerator(
            $this->router,
            $this->routeDescriber,
            $this->operationDescriber,
            $this->schemaRegistry,
            null,
            $config,
            $logger,
        );

        $route = new Route('/api/users', ['_controller' => 'TestController::index']);

        $routes = [
            'app_users' => [
                'route' => $route,
                'controller' => 'TestController',
                'method' => 'index',
                'reflection' => new \ReflectionMethod($this::class, 'testGenerateWithPaths'),
            ],
        ];

        $this->routeDescriber->expects($this->once())
            ->method('describe')
            ->willReturn($routes);

        $this->schemaRegistry->method('getSchemas')->willReturn([]);

        // Operation describer throws exception
        $this->operationDescriber->expects($this->once())
            ->method('describe')
            ->willThrowException(new \RuntimeException('Test error'));

        $logger->expects($this->once())
            ->method('warning');

        $result = $generator->generate();

        // Should still return valid OpenAPI doc, just without this path
        $this->assertArrayHasKey('paths', $result);
    }

    public function testDefaultCacheTtl(): void
    {
        $cache = $this->createMock(CacheInterface::class);

        $config = [
            'info' => ['title' => 'Test', 'description' => '', 'version' => '1.0.0'],
            'servers' => [],
            'enabled' => true,
            'output_path' => '/tmp/swagger.json',
            'cache' => ['enabled' => true],
        ];

        $generator = new OpenApiGenerator(
            $this->router,
            $this->routeDescriber,
            $this->operationDescriber,
            $this->schemaRegistry,
            $cache,
            $config,
            null,
        );

        $this->schemaRegistry->method('getSchemas')->willReturn([]);

        $cache->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->expects($this->once())
                    ->method('expiresAfter')
                    ->with(3600);

                return $callback($item);
            });

        $generator->generate();
    }
}
