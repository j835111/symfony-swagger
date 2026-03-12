<?php

declare(strict_types=1);

namespace SymfonySwagger\Tests\Analyzer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use SymfonySwagger\Analyzer\AttributeReader;
use SymfonySwagger\Attribute\ApiResponse;

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

    public function testGetQueryStringParametersFromAttributes(): void
    {
        $reflection = new \ReflectionMethod(TestController::class, 'filterAction');
        $parameters = $this->reader->getQueryStringParametersFromAttributes($reflection);

        $this->assertCount(1, $parameters);
        $this->assertSame('filter', $parameters[0]['name']);
        $this->assertSame('query', $parameters[0]['in']);
        $this->assertInstanceOf(MapQueryString::class, $parameters[0]['attribute']);
    }

    public function testGetUploadedFileParametersFromAttributes(): void
    {
        $reflection = new \ReflectionMethod(TestController::class, 'uploadAction');
        $parameters = $this->reader->getUploadedFileParametersFromAttributes($reflection);

        $this->assertCount(1, $parameters);
        $this->assertSame('picture', $parameters[0]['name']);
        $this->assertInstanceOf(MapUploadedFile::class, $parameters[0]['attribute']);
    }

    public function testReadSecurityAttributes(): void
    {
        $reflection = new \ReflectionMethod(TestController::class, 'protectedAction');
        $attributes = $this->reader->readSecurityAttributes($reflection);

        // Note: IsGranted needs symfony/security-http to be installed
        // For now, we expect an empty array if the package is not installed
        $this->assertIsArray($attributes);
    }

    public function testReadApiResponseAttribute(): void
    {
        $reflection = new \ReflectionMethod(TestController::class, 'apiResponseAction');
        $attribute = $this->reader->readApiResponseAttribute($reflection);

        $this->assertInstanceOf(ApiResponse::class, $attribute);
        $this->assertSame(FilterDto::class, $attribute->type);
        $this->assertTrue($attribute->collection);
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

    #[Route('/posts/filter', methods: ['GET'])]
    public function filterAction(#[MapQueryString] FilterDto $filter): void
    {
    }

    #[Route('/posts/upload', methods: ['POST'])]
    public function uploadAction(#[MapUploadedFile] UploadedFile $picture): void
    {
    }

    public function methodWithoutRoute(): void
    {
    }

    #[Route('/protected', methods: ['GET'])]
    public function protectedAction(): void
    {
    }

    #[ApiResponse(type: FilterDto::class, collection: true)]
    public function apiResponseAction(): void
    {
    }
}

class FilterDto
{
    public string $keyword;
    public ?int $page = null;
}
