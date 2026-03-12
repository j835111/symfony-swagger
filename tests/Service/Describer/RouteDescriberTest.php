<?php

declare(strict_types=1);

namespace SymfonySwagger\Tests\Service\Describer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use SymfonySwagger\Service\Describer\RouteDescriber;

/**
 * Test case for RouteDescriber.
 */
class RouteDescriberTest extends TestCase
{
    private RouteDescriber $describer;

    protected function setUp(): void
    {
        $this->describer = new RouteDescriber();
    }

    public function testDescribeWithEmptyRoutes(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());

        $result = $this->describer->describe($router);

        $this->assertEmpty($result);
    }

    public function testDescribeFiltersInternalRoutesByDefault(): void
    {
        $router = $this->createMock(RouterInterface::class);

        $routes = new RouteCollection();
        $routes->add('_profiler_home', new Route('/_profiler', ['_controller' => self::class.'::testDescribeWithEmptyRoutes']));
        $routes->add('app_users', new Route('/api/users', ['_controller' => self::class.'::testDescribeFiltersInternalRoutesByDefault']));

        $router->method('getRouteCollection')->willReturn($routes);

        $result = $this->describer->describe($router);

        $this->assertArrayNotHasKey('_profiler_home', $result);
        $this->assertArrayHasKey('app_users', $result);
    }

    public function testDescribeIncludesInternalRoutesWhenEnabled(): void
    {
        $router = $this->createMock(RouterInterface::class);

        $routes = new RouteCollection();
        $routes->add('_profiler_home', new Route('/_profiler', ['_controller' => self::class.'::testDescribeIncludesInternalRoutesWhenEnabled']));

        $router->method('getRouteCollection')->willReturn($routes);

        $config = ['analysis' => ['include_internal_routes' => true]];
        $result = $this->describer->describe($router, $config);

        $this->assertArrayHasKey('_profiler_home', $result);
    }

    public function testDescribeFiltersRoutesWithoutController(): void
    {
        $router = $this->createMock(RouterInterface::class);

        $routes = new RouteCollection();
        $routes->add('app_users', new Route('/api/users'));

        $router->method('getRouteCollection')->willReturn($routes);

        $result = $this->describer->describe($router);

        $this->assertEmpty($result);
    }

    public function testDescribeWithValidController(): void
    {
        $router = $this->createMock(RouterInterface::class);

        $routes = new RouteCollection();
        $routes->add('app_users', new Route('/api/users', ['_controller' => self::class.'::testDescribeWithValidController']));

        $router->method('getRouteCollection')->willReturn($routes);

        $result = $this->describer->describe($router);

        $this->assertArrayHasKey('app_users', $result);
        $this->assertArrayHasKey('route', $result['app_users']);
        $this->assertArrayHasKey('reflection', $result['app_users']);
    }

    public function testDescribeSkipsInvalidControllerClass(): void
    {
        $router = $this->createMock(RouterInterface::class);

        $routes = new RouteCollection();
        $routes->add('app_users', new Route('/api/users', ['_controller' => 'NonExistentClass::index']));

        $router->method('getRouteCollection')->willReturn($routes);

        $result = $this->describer->describe($router);

        $this->assertEmpty($result);
    }

    public function testDescribeSkipsInvalidControllerMethod(): void
    {
        $router = $this->createMock(RouterInterface::class);

        $routes = new RouteCollection();
        $routes->add('app_users', new Route('/api/users', ['_controller' => self::class.'::nonExistentMethod']));

        $router->method('getRouteCollection')->willReturn($routes);

        $result = $this->describer->describe($router);

        $this->assertEmpty($result);
    }

    public function testDescribeReturnsRouteInfo(): void
    {
        $router = $this->createMock(RouterInterface::class);

        $routes = new RouteCollection();
        $routes->add('app_users', new Route('/api/users', ['_controller' => self::class.'::testDescribeReturnsRouteInfo']));

        $router->method('getRouteCollection')->willReturn($routes);

        $result = $this->describer->describe($router);

        $this->assertEquals(self::class, $result['app_users']['controller']);
        $this->assertEquals('testDescribeReturnsRouteInfo', $result['app_users']['method']);
    }

    public function testDescribeWithMultipleRoutes(): void
    {
        $router = $this->createMock(RouterInterface::class);

        $routes = new RouteCollection();
        $routes->add('app_users', new Route('/api/users', ['_controller' => self::class.'::testDescribeWithMultipleRoutes']));
        $routes->add('app_user_detail', new Route('/api/users/{id}', ['_controller' => self::class.'::testDescribeWithMultipleRoutes']));
        $routes->add('app_posts', new Route('/api/posts', ['_controller' => self::class.'::testDescribeWithMultipleRoutes']));

        $router->method('getRouteCollection')->willReturn($routes);

        $result = $this->describer->describe($router);

        $this->assertNotEmpty($result);
    }

    public function testDescribeWithNonStringController(): void
    {
        $router = $this->createMock(RouterInterface::class);

        $routes = new RouteCollection();
        $routes->add('app_users', new Route('/api/users', ['_controller' => fn () => 'test']));

        $router->method('getRouteCollection')->willReturn($routes);

        $result = $this->describer->describe($router);

        $this->assertEmpty($result);
    }

    public function testDescribeWithArrayControllerFormat(): void
    {
        $router = $this->createMock(RouterInterface::class);

        $routes = new RouteCollection();
        $routes->add('app_users', new Route('/api/users', ['_controller' => [self::class, 'testDescribeWithArrayControllerFormat']]));

        $router->method('getRouteCollection')->willReturn($routes);

        $result = $this->describer->describe($router);

        $this->assertEmpty($result);
    }

    public function testDescribeWithCallableController(): void
    {
        $router = $this->createMock(RouterInterface::class);

        $controller = new class () {
            public function __invoke()
            {
            }
        };

        $routes = new RouteCollection();
        $routes->add('app_invokable', new Route('/api/test', ['_controller' => $controller]));

        $router->method('getRouteCollection')->willReturn($routes);

        $result = $this->describer->describe($router);

        $this->assertEmpty($result);
    }
}
