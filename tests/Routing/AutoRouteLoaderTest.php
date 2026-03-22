<?php

declare(strict_types=1);

namespace SymfonySwagger\Tests\Routing;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\Routing\RouteCollection;
use SymfonySwagger\Routing\AutoRouteLoader;

class AutoRouteLoaderTest extends TestCase
{
    public function testLoadAutomaticallyAddsDocumentationRoutes(): void
    {
        $inner = new StubLoader(new RouteCollection());
        $loader = new AutoRouteLoader($inner);

        $routes = $loader->load('ignored');

        $this->assertInstanceOf(RouteCollection::class, $routes);
        $this->assertSame('/api/docs.json', $routes->get('symfony_swagger_doc')?->getPath());
        $this->assertSame('/api/docs', $routes->get('symfony_swagger_ui')?->getPath());
        $this->assertSame('/api/docs/scalar', $routes->get('symfony_swagger_scalar')?->getPath());
    }

    public function testLoadRespectsDisabledFlag(): void
    {
        $inner = new StubLoader(new RouteCollection());
        $loader = new AutoRouteLoader($inner, false);

        $routes = $loader->load('ignored');

        $this->assertInstanceOf(RouteCollection::class, $routes);
        $this->assertNull($routes->get('symfony_swagger_doc'));
    }

    public function testLoadReturnsNonRouteCollectionUnchanged(): void
    {
        $inner = new StubLoader('plain-result');
        $loader = new AutoRouteLoader($inner);

        $result = $loader->load('ignored');

        $this->assertSame('plain-result', $result);
    }

    public function testSupportsDelegatesToInnerLoader(): void
    {
        $inner = new StubLoader(new RouteCollection());
        $loader = new AutoRouteLoader($inner);

        $this->assertTrue($loader->supports('ignored', 'php'));
    }

    public function testResolverMethodsDelegateToInnerLoader(): void
    {
        $inner = new StubLoader(new RouteCollection());
        $loader = new AutoRouteLoader($inner);
        $this->assertSame($inner->resolver, $loader->getResolver());

        $otherResolver = $this->createMock(LoaderResolverInterface::class);
        $loader->setResolver($otherResolver);

        $this->assertSame($otherResolver, $inner->resolver);
    }
}

class StubLoader implements LoaderInterface
{
    public LoaderResolverInterface $resolver;

    public function __construct(
        private readonly mixed $result,
    ) {
        $this->resolver = new class () implements LoaderResolverInterface {
            public function resolve(mixed $resource, ?string $type = null): LoaderInterface|false
            {
                return false;
            }
        };
    }

    public function load(mixed $resource, ?string $type = null): mixed
    {
        return $this->result;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return true;
    }

    public function getResolver(): LoaderResolverInterface
    {
        return $this->resolver;
    }

    public function setResolver(LoaderResolverInterface $resolver): void
    {
        $this->resolver = $resolver;
    }
}
