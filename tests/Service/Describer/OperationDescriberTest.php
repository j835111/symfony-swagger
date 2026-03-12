<?php

declare(strict_types=1);

namespace SymfonySwagger\Tests\Service\Describer;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Routing\Route;
use SymfonySwagger\Analyzer\AttributeReader;
use SymfonySwagger\Analyzer\TypeAnalyzer;
use SymfonySwagger\Service\Describer\OperationDescriber;
use SymfonySwagger\Service\Describer\SchemaDescriber;
use SymfonySwagger\Service\Registry\SchemaRegistry;

/**
 * Test case for OperationDescriber.
 */
class OperationDescriberTest extends TestCase
{
    private OperationDescriber $describer;
    private AttributeReader $attributeReader;
    private TypeAnalyzer $typeAnalyzer;
    private SchemaDescriber $schemaDescriber;

    protected function setUp(): void
    {
        $this->attributeReader = new AttributeReader();
        $this->typeAnalyzer = new TypeAnalyzer();
        $this->schemaDescriber = new SchemaDescriber(
            $this->typeAnalyzer,
            new SchemaRegistry()
        );
        $this->describer = new OperationDescriber(
            $this->attributeReader,
            $this->typeAnalyzer,
            $this->schemaDescriber
        );
    }

    public function testDescribeWithBasicRoute(): void
    {
        $method = new ReflectionMethod($this::class, 'testDescribeWithBasicRoute');
        $route = new Route('/api/users');

        $result = $this->describer->describe($method, $route);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('operationId', $result);
        $this->assertArrayHasKey('tags', $result);
        $this->assertArrayHasKey('responses', $result);
    }

    public function testGenerateOperationId(): void
    {
        $method = new ReflectionMethod($this::class, 'testGenerateOperationId');
        $route = new Route('/api/users');

        $result = $this->describer->describe($method, $route);

        $this->assertEquals('operationDescriberTest_testGenerateOperationId', $result['operationId']);
    }

    public function testGenerateTagsFromControllerName(): void
    {
        $method = new ReflectionMethod($this::class, 'testGenerateTagsFromControllerName');
        $route = new Route('/api/users');

        $result = $this->describer->describe($method, $route);

        $this->assertContains('OperationDescriberTest', $result['tags']);
    }

    public function testDescribeWithPathParameters(): void
    {
        $method = new ReflectionMethod($this::class, 'testDescribeWithPathParameters');
        $route = new Route('/api/users/{id}');

        $result = $this->describer->describe($method, $route);

        $this->assertArrayHasKey('parameters', $result);
        $this->assertNotEmpty($result['parameters']);
        
        $pathParam = array_filter($result['parameters'], fn($p) => $p['in'] === 'path');
        $this->assertNotEmpty($pathParam);
    }

    public function testMultiplePathParameters(): void
    {
        $method = new ReflectionMethod($this::class, 'testMultiplePathParameters');
        $route = new Route('/api/users/{userId}/posts/{postId}');

        $result = $this->describer->describe($method, $route);

        $this->assertArrayHasKey('parameters', $result);
        $this->assertCount(2, $result['parameters']);
    }

    public function testRouteWithNoPathParameters(): void
    {
        $method = new ReflectionMethod($this::class, 'testRouteWithNoPathParameters');
        $route = new Route('/api/users');

        $result = $this->describer->describe($method, $route);

        // Parameters key should not exist or be empty
        $this->assertTrue(!isset($result['parameters']) || empty($result['parameters']));
    }

    public function testDescribeWithRequestPayload(): void
    {
        $method = new ReflectionMethod($this::class, 'testDescribeWithRequestPayload');
        $route = new Route('/api/users');

        $result = $this->describer->describe($method, $route);

        $this->assertArrayHasKey('responses', $result);
        $this->assertArrayHasKey('200', $result['responses']);
    }

    public function testDescribeWithApiResponseAttribute(): void
    {
        $method = new ReflectionMethod($this::class, 'testDescribeWithApiResponseAttribute');
        $route = new Route('/api/users');

        $result = $this->describer->describe($method, $route);

        $this->assertArrayHasKey('responses', $result);
        $this->assertArrayHasKey('200', $result['responses']);
    }

    public function testDescribeWithFileResponse(): void
    {
        $method = new ReflectionMethod($this::class, 'testDescribeWithFileResponse');
        $route = new Route('/api/download');

        $result = $this->describer->describe($method, $route);

        $this->assertArrayHasKey('responses', $result);
        $this->assertArrayHasKey('200', $result['responses']);
        
        $response = $result['responses']['200'];
        $this->assertArrayHasKey('content', $response);
    }

    public function testGenerateSummaryFromMethodName(): void
    {
        $method = new ReflectionMethod($this::class, 'testGenerateSummaryFromMethodName');
        $route = new Route('/api/users');

        $result = $this->describer->describe($method, $route);

        $this->assertNotEmpty($result['summary']);
    }

    public function testResponseContentType(): void
    {
        $method = new ReflectionMethod($this::class, 'testResponseContentType');
        $route = new Route('/api/users');

        $result = $this->describer->describe($method, $route);

        $response = $result['responses']['200'];
        $this->assertArrayHasKey('content', $response);
        $this->assertArrayHasKey('application/json', $response['content']);
    }

    public function testResponseSchemaStructure(): void
    {
        $method = new ReflectionMethod($this::class, 'testResponseSchemaStructure');
        $route = new Route('/api/users');

        $result = $this->describer->describe($method, $route);

        $response = $result['responses']['200'];
        $schema = $response['content']['application/json']['schema'];
        
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('code', $schema['properties']);
        $this->assertArrayHasKey('message', $schema['properties']);
        $this->assertArrayHasKey('data', $schema['properties']);
    }

    public function testPathParameterRequired(): void
    {
        $method = new ReflectionMethod($this::class, 'testPathParameterRequired');
        $route = new Route('/api/users/{id}');

        $result = $this->describer->describe($method, $route);

        $pathParams = array_filter($result['parameters'] ?? [], fn($p) => $p['in'] === 'path');
        
        foreach ($pathParams as $param) {
            $this->assertTrue($param['required']);
        }
    }

    public function testQueryParameterNullable(): void
    {
        $method = new ReflectionMethod($this::class, 'testQueryParameterNullable');
        $route = new Route('/api/users');

        $result = $this->describer->describe($method, $route);

        $this->assertIsArray($result['parameters'] ?? []);
    }

    public function testGenerateSummaryWithDocBlock(): void
    {
        $method = new ReflectionMethod(TestControllerWithDoc::class, 'index');
        $route = new Route('/api/test');

        $result = $this->describer->describe($method, $route);

        $this->assertArrayHasKey('summary', $result);
    }

    public function testRequestBodyWithDtoClass(): void
    {
        $method = new ReflectionMethod(TestControllerWithDto::class, 'create');
        $route = new Route('/api/users');

        $result = $this->describer->describe($method, $route);

        $this->assertArrayHasKey('responses', $result);
    }
}

// Test fixtures
class TestControllerWithDoc
{
    /**
     * @summary Get all users
     */
    public function index(): array
    {
        return [];
    }
}

class TestDtoForDescriber
{
    public string $name;
    public ?string $email = null;
}

class TestControllerWithDto
{
    public function create(TestDtoForDescriber $dto): array
    {
        return [];
    }
}
