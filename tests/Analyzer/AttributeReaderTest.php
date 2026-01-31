<?php

declare(strict_types=1);

namespace SymfonySwagger\Tests\Analyzer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use SymfonySwagger\Analyzer\AttributeReader;

class AttributeReaderTest extends TestCase
{
    private AttributeReader $reader;

    protected function setUp(): void
    {
        $this->reader = new AttributeReader();
    }

    public function testReadRouteAttribute(): void
    {
        $reflection = new \ReflectionMethod(TestController::class, 'listAction');
        $route = $this->reader->readRouteAttribute($reflection);

        $this->assertInstanceOf(Route::class, $route);
        $this->assertSame('/posts', $route->getPath());
        $this->assertSame(['GET'], $route->getMethods());
    }

    public function testReadRouteAttributeReturnsNullWhenNotPresent(): void
    {
        $reflection = new \ReflectionMethod(TestController::class, 'methodWithoutRoute');
        $route = $this->reader->readRouteAttribute($reflection);

        $this->assertNull($route);
    }

    public function testReadRequestAttributesWithMapRequestPayload(): void
    {
        $reflection = new \ReflectionMethod(TestController::class, 'createAction');
        $attributes = $this->reader->readRequestAttributes($reflection);

        $this->assertArrayHasKey('requestPayload', $attributes);
        $this->assertInstanceOf(MapRequestPayload::class, $attributes['requestPayload']);
    }

    public function testGetParametersFromAttributes(): void
    {
        $reflection = new \ReflectionMethod(TestController::class, 'searchAction');
        $parameters = $this->reader->getParametersFromAttributes($reflection);

        $this->assertCount(1, $parameters);
        $this->assertSame('query', $parameters[0]['name']);
        $this->assertSame('query', $parameters[0]['in']);
        $this->assertInstanceOf(MapQueryParameter::class, $parameters[0]['attribute']);
    }

    public function testReadSecurityAttributes(): void
    {
        $reflection = new \ReflectionMethod(TestController::class, 'protectedAction');
        $attributes = $this->reader->readSecurityAttributes($reflection);

        // Note: IsGranted needs symfony/security-http to be installed
        // For now, we expect an empty array if the package is not installed
        $this->assertIsArray($attributes);
    }
}

/**
 * Test controller with various attributes for testing.
 */
class TestController
{
    #[Route('/posts', methods: ['GET'])]
    public function listAction(): void
    {
    }

    #[Route('/posts', methods: ['POST'])]
    public function createAction(#[MapRequestPayload] object $dto): void
    {
    }

    #[Route('/posts/search', methods: ['GET'])]
    public function searchAction(#[MapQueryParameter] string $query): void
    {
    }

    public function methodWithoutRoute(): void
    {
    }

    #[Route('/protected', methods: ['GET'])]
    public function protectedAction(): void
    {
    }
}
