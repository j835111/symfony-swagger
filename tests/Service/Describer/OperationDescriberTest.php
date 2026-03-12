<?php

declare(strict_types=1);

namespace SymfonySwagger\Tests\Service\Describer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
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
            new SchemaRegistry(),
        );
        $this->describer = new OperationDescriber(
            $this->attributeReader,
            $this->typeAnalyzer,
            $this->schemaDescriber,
        );
    }

    public function testDescribeWithBasicRoute(): void
    {
        $method = new \ReflectionMethod($this::class, 'testDescribeWithBasicRoute');
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
        $method = new \ReflectionMethod($this::class, 'testGenerateOperationId');
        $route = new Route('/api/users');

        $result = $this->describer->describe($method, $route);

        $this->assertEquals('operationDescriberTest_testGenerateOperationId', $result['operationId']);
    }

    public function testGenerateTagsFromControllerName(): void
    {
        $method = new \ReflectionMethod($this::class, 'testGenerateTagsFromControllerName');
        $route = new Route('/api/users');

        $result = $this->describer->describe($method, $route);

        $this->assertContains('OperationDescriberTest', $result['tags']);
    }

    public function testDescribeWithPathParameters(): void
    {
        $method = new \ReflectionMethod($this::class, 'testDescribeWithPathParameters');
        $route = new Route('/api/users/{id}');

        $result = $this->describer->describe($method, $route);

        $this->assertArrayHasKey('parameters', $result);
        $this->assertNotEmpty($result['parameters']);

        $pathParam = array_filter($result['parameters'], fn ($p) => 'path' === $p['in']);
        $this->assertNotEmpty($pathParam);
    }

    public function testMultiplePathParameters(): void
    {
        $method = new \ReflectionMethod($this::class, 'testMultiplePathParameters');
        $route = new Route('/api/users/{userId}/posts/{postId}');

        $result = $this->describer->describe($method, $route);

        $this->assertArrayHasKey('parameters', $result);
        $this->assertCount(2, $result['parameters']);
    }

    public function testRouteWithNoPathParameters(): void
    {
        $method = new \ReflectionMethod($this::class, 'testRouteWithNoPathParameters');
        $route = new Route('/api/users');

        $result = $this->describer->describe($method, $route);

        // Parameters key should not exist or be empty
        $this->assertTrue(!isset($result['parameters']) || empty($result['parameters']));
    }

    public function testDescribeWithRequestPayload(): void
    {
        $method = new \ReflectionMethod($this::class, 'testDescribeWithRequestPayload');
        $route = new Route('/api/users');

        $result = $this->describer->describe($method, $route);

        $this->assertArrayHasKey('responses', $result);
        $this->assertArrayHasKey('200', $result['responses']);
    }

    public function testDescribeWithApiResponseAttribute(): void
    {
        $method = new \ReflectionMethod($this::class, 'testDescribeWithApiResponseAttribute');
        $route = new Route('/api/users');

        $result = $this->describer->describe($method, $route);

        $this->assertArrayHasKey('responses', $result);
        $this->assertArrayHasKey('200', $result['responses']);
    }

    public function testDescribeWithFileResponse(): void
    {
        $method = new \ReflectionMethod($this::class, 'testDescribeWithFileResponse');
        $route = new Route('/api/download');

        $result = $this->describer->describe($method, $route);

        $this->assertArrayHasKey('responses', $result);
        $this->assertArrayHasKey('200', $result['responses']);

        $response = $result['responses']['200'];
        $this->assertArrayHasKey('content', $response);
    }

    public function testGenerateSummaryFromMethodName(): void
    {
        $method = new \ReflectionMethod($this::class, 'testGenerateSummaryFromMethodName');
        $route = new Route('/api/users');

        $result = $this->describer->describe($method, $route);

        $this->assertNotEmpty($result['summary']);
    }

    public function testResponseContentType(): void
    {
        $method = new \ReflectionMethod($this::class, 'testResponseContentType');
        $route = new Route('/api/users');

        $result = $this->describer->describe($method, $route);

        $response = $result['responses']['200'];
        $this->assertArrayHasKey('content', $response);
        $this->assertArrayHasKey('application/json', $response['content']);
    }

    public function testResponseSchemaStructure(): void
    {
        $method = new \ReflectionMethod($this::class, 'testResponseSchemaStructure');
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
        $method = new \ReflectionMethod($this::class, 'testPathParameterRequired');
        $route = new Route('/api/users/{id}');

        $result = $this->describer->describe($method, $route);

        $pathParams = array_filter($result['parameters'] ?? [], fn ($p) => 'path' === $p['in']);

        foreach ($pathParams as $param) {
            $this->assertTrue($param['required']);
        }
    }

    public function testQueryParameterNullable(): void
    {
        $method = new \ReflectionMethod($this::class, 'testQueryParameterNullable');
        $route = new Route('/api/users');

        $result = $this->describer->describe($method, $route);

        $this->assertIsArray($result['parameters'] ?? []);
    }

    public function testGenerateSummaryWithDocBlock(): void
    {
        $method = new \ReflectionMethod(TestControllerWithDoc::class, 'index');
        $route = new Route('/api/test');

        $result = $this->describer->describe($method, $route);

        $this->assertArrayHasKey('summary', $result);
    }

    public function testRequestBodyWithDtoClass(): void
    {
        $method = new \ReflectionMethod(TestControllerWithDto::class, 'create');
        $route = new Route('/api/users');

        $result = $this->describer->describe($method, $route);

        $this->assertArrayHasKey('responses', $result);
    }

    public function testDescribeWithMapRequestPayloadDto(): void
    {
        if (!class_exists(MapRequestPayload::class)) {
            $this->markTestSkipped('MapRequestPayload not available.');
        }

        $method = new \ReflectionMethod(TestControllerWithRequestPayload::class, 'create');
        $route = new Route('/api/users');

        $result = $this->describer->describe($method, $route);

        $this->assertArrayHasKey('requestBody', $result);
        $schema = $result['requestBody']['content']['application/json']['schema'];
        $this->assertSame('#/components/schemas/TestDtoForDescriber', $schema['$ref']);
    }

    public function testDescribeWithMapRequestPayloadArrayType(): void
    {
        if (!class_exists(MapRequestPayload::class)) {
            $this->markTestSkipped('MapRequestPayload not available.');
        }

        $method = new \ReflectionMethod(TestControllerWithRequestPayload::class, 'bulkCreate');
        $route = new Route('/api/users/bulk');

        $result = $this->describer->describe($method, $route);

        $this->assertArrayHasKey('requestBody', $result);
        $schema = $result['requestBody']['content']['application/json']['schema'];
        $this->assertSame('array', $schema['type']);
        $this->assertSame('#/components/schemas/TestDtoForDescriber', $schema['items']['$ref']);
    }

    public function testDescribeWithMapQueryStringFlattensParameters(): void
    {
        if (!class_exists(MapQueryString::class)) {
            $this->markTestSkipped('MapQueryString not available.');
        }

        $method = new \ReflectionMethod(TestControllerWithQueryString::class, 'list');
        $route = new Route('/api/users');

        $result = $this->describer->describe($method, $route);

        $params = $result['parameters'] ?? [];
        $this->assertNotEmpty($params);

        $byName = [];
        foreach ($params as $param) {
            $byName[$param['name']] = $param;
        }

        $this->assertArrayHasKey('keyword', $byName);
        $this->assertArrayHasKey('page', $byName);
        $this->assertTrue($byName['keyword']['required']);
        $this->assertFalse($byName['page']['required']);
    }

    public function testDescribeWithMapQueryStringKeyUsesDeepObject(): void
    {
        if (!class_exists(MapQueryString::class)) {
            $this->markTestSkipped('MapQueryString not available.');
        }

        $method = new \ReflectionMethod(TestControllerWithQueryString::class, 'search');
        $route = new Route('/api/users/search');

        $result = $this->describer->describe($method, $route);

        $params = $result['parameters'] ?? [];
        $param = array_values(array_filter($params, fn ($p) => 'filter' === $p['name']))[0] ?? null;
        $this->assertNotNull($param);
        $this->assertSame('deepObject', $param['style']);
        $this->assertTrue($param['explode']);
        $this->assertSame('object', $param['schema']['type']);
    }

    public function testDescribeWithMapUploadedFileSingle(): void
    {
        if (!class_exists(MapUploadedFile::class) || !class_exists(UploadedFile::class)) {
            $this->markTestSkipped('MapUploadedFile or UploadedFile not available.');
        }

        $method = new \ReflectionMethod(TestControllerWithUploads::class, 'upload');
        $route = new Route('/api/upload');

        $result = $this->describer->describe($method, $route);

        $this->assertArrayHasKey('requestBody', $result);
        $content = $result['requestBody']['content']['multipart/form-data']['schema'];
        $this->assertArrayHasKey('file', $content['properties']);
        $this->assertSame('string', $content['properties']['file']['type']);
        $this->assertSame('binary', $content['properties']['file']['format']);
    }

    public function testDescribeWithMapUploadedFileArray(): void
    {
        if (!class_exists(MapUploadedFile::class) || !class_exists(UploadedFile::class)) {
            $this->markTestSkipped('MapUploadedFile or UploadedFile not available.');
        }

        $method = new \ReflectionMethod(TestControllerWithUploads::class, 'uploadMany');
        $route = new Route('/api/upload-many');

        $result = $this->describer->describe($method, $route);

        $this->assertArrayHasKey('requestBody', $result);
        $content = $result['requestBody']['content']['multipart/form-data']['schema'];
        $this->assertArrayHasKey('files', $content['properties']);
        $this->assertSame('array', $content['properties']['files']['type']);
        $this->assertSame('string', $content['properties']['files']['items']['type']);
        $this->assertSame('binary', $content['properties']['files']['items']['format']);
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

class QueryFilterDto
{
    public string $keyword;
    public ?int $page = null;
}

class TestControllerWithQueryString
{
    public function list(#[MapQueryString] QueryFilterDto $filter): array
    {
        return [];
    }

    public function search(#[MapQueryString(key: 'filter')] QueryFilterDto $filter): array
    {
        return [];
    }
}

class TestControllerWithRequestPayload
{
    public function create(#[MapRequestPayload] TestDtoForDescriber $dto): array
    {
        return [];
    }

    public function bulkCreate(#[MapRequestPayload(type: TestDtoForDescriber::class)] array $items): array
    {
        return [];
    }
}

class TestControllerWithUploads
{
    public function upload(#[MapUploadedFile(name: 'file')] UploadedFile $file): array
    {
        return [];
    }

    public function uploadMany(#[MapUploadedFile(name: 'files')] array $files): array
    {
        return [];
    }
}
