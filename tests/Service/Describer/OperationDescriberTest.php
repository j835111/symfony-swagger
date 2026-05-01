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

    public function testDescribeWithMethodSecurityAttributeAddsSecurityRequirement(): void
    {
        $method = new \ReflectionMethod(TestControllerWithSecurity::class, 'secured');
        $route = new Route('/api/secured');

        $result = $this->describer->describe($method, $route, 'GET');

        $this->assertSame([['defaultAuth' => []]], $result['security']);
    }

    public function testDescribeWithClassSecurityAttributeAddsSecurityRequirement(): void
    {
        $method = new \ReflectionMethod(TestClassSecuredController::class, 'index');
        $route = new Route('/api/secured');

        $result = $this->describer->describe($method, $route, 'GET');

        $this->assertSame([['defaultAuth' => []]], $result['security']);
    }

    public function testDescribeUsesConfiguredDefaultSecurityScheme(): void
    {
        $describer = new OperationDescriber(
            $this->attributeReader,
            $this->typeAnalyzer,
            $this->schemaDescriber,
            ['security' => ['default_scheme' => 'sessionAuth']],
        );
        $method = new \ReflectionMethod(TestControllerWithSecurity::class, 'secured');
        $route = new Route('/api/secured');

        $result = $describer->describe($method, $route, 'GET');

        $this->assertSame([['sessionAuth' => []]], $result['security']);
    }

    public function testDescribeDoesNotAddSecurityWhenDisabled(): void
    {
        $describer = new OperationDescriber(
            $this->attributeReader,
            $this->typeAnalyzer,
            $this->schemaDescriber,
            ['security' => ['enabled' => false]],
        );
        $method = new \ReflectionMethod(TestControllerWithSecurity::class, 'secured');
        $route = new Route('/api/secured');

        $result = $describer->describe($method, $route, 'GET');

        $this->assertArrayNotHasKey('security', $result);
    }

    public function testDescribeHonorsSecurityAttributeMethodFilter(): void
    {
        $method = new \ReflectionMethod(TestControllerWithSecurity::class, 'writeOnly');
        $route = new Route('/api/write-only', methods: ['GET', 'POST']);

        $getResult = $this->describer->describe($method, $route, 'GET');
        $postResult = $this->describer->describe($method, $route, 'POST');

        $this->assertArrayNotHasKey('security', $getResult);
        $this->assertSame([['defaultAuth' => []]], $postResult['security']);
    }

    public function testDescribeInfersSingleRouteMethodForSecurityAttributeMethodFilter(): void
    {
        $method = new \ReflectionMethod(TestControllerWithSecurity::class, 'writeOnly');
        $route = new Route('/api/write-only', methods: ['POST']);

        $result = $this->describer->describe($method, $route);

        $this->assertSame([['defaultAuth' => []]], $result['security']);
    }

    public function testDescribeAppliesSecurityWhenMethodFilterCannotBeResolved(): void
    {
        $method = new \ReflectionMethod(TestControllerWithSecurity::class, 'writeOnly');
        $route = new Route('/api/write-only', methods: ['GET', 'POST']);

        $result = $this->describer->describe($method, $route);

        $this->assertSame([['defaultAuth' => []]], $result['security']);
    }

    public function testDescribeHonorsStringSecurityAttributeMethodFilter(): void
    {
        $method = new \ReflectionMethod(TestControllerWithSecurity::class, 'postOnlyStringMethod');
        $route = new Route('/api/post-only', methods: ['GET', 'POST']);

        $getResult = $this->describer->describe($method, $route, 'GET');
        $postResult = $this->describer->describe($method, $route, 'POST');

        $this->assertArrayNotHasKey('security', $getResult);
        $this->assertSame([['defaultAuth' => []]], $postResult['security']);
    }

    public function testDescribeDoesNotMarkPublicAccessEndpointAsSecured(): void
    {
        $method = new \ReflectionMethod(TestControllerWithSecurity::class, 'publicAccess');
        $route = new Route('/api/public');

        $result = $this->describer->describe($method, $route, 'GET');

        $this->assertArrayNotHasKey('security', $result);
    }

    public function testDescribeDoesNotMarkPublicAccessArrayEndpointAsSecured(): void
    {
        $method = new \ReflectionMethod(TestControllerWithSecurity::class, 'publicAccessArray');
        $route = new Route('/api/public-array');

        $result = $this->describer->describe($method, $route, 'GET');

        $this->assertArrayNotHasKey('security', $result);
    }

    public function testSecurityAttributeWithoutMethodsPropertyApplies(): void
    {
        $resolver = \Closure::bind(
            fn (object $attribute): bool => $this->securityAttributeAppliesToMethod($attribute, 'GET'),
            $this->describer,
            OperationDescriber::class,
        );

        $this->assertTrue($resolver(new class () {
        }));
    }

    public function testSecurityAttributeWithUnsupportedMethodsPropertyApplies(): void
    {
        $resolver = \Closure::bind(
            fn (object $attribute): bool => $this->securityAttributeAppliesToMethod($attribute, 'GET'),
            $this->describer,
            OperationDescriber::class,
        );

        $this->assertTrue($resolver(new class () {
            public int $methods = 1;
        }));
    }

    public function testSecurityAttributeWithoutAttributePropertyIsNotPublicAccess(): void
    {
        $resolver = \Closure::bind(
            fn (object $attribute): bool => $this->isPublicAccessSecurityAttribute($attribute),
            $this->describer,
            OperationDescriber::class,
        );

        $this->assertFalse($resolver(new class () {
        }));
    }

    public function testSecurityAttributeWithUnsupportedAttributePropertyIsNotPublicAccess(): void
    {
        $resolver = \Closure::bind(
            fn (object $attribute): bool => $this->isPublicAccessSecurityAttribute($attribute),
            $this->describer,
            OperationDescriber::class,
        );

        $this->assertFalse($resolver(new class () {
            public int $attribute = 1;
        }));
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

    public function testPathParameterUsesControllerParameterType(): void
    {
        $method = new \ReflectionMethod(TestControllerWithPathParameters::class, 'show');
        $route = new Route('/api/users/{id}');

        $result = $this->describer->describe($method, $route);

        $pathParams = array_values(array_filter($result['parameters'] ?? [], fn ($p) => 'path' === $p['in']));
        $this->assertCount(1, $pathParams);
        $this->assertSame('integer', $pathParams[0]['schema']['type']);
        $this->assertSame('int32', $pathParams[0]['schema']['format']);
    }

    public function testPathParameterFallsBackToRouteRequirement(): void
    {
        $method = new \ReflectionMethod(TestControllerWithPathParameters::class, 'slug');
        $route = new Route('/api/orders/{orderId}', requirements: ['orderId' => '\d+']);

        $result = $this->describer->describe($method, $route);

        $pathParams = array_values(array_filter($result['parameters'] ?? [], fn ($p) => 'path' === $p['in']));
        $this->assertCount(1, $pathParams);
        $this->assertSame('integer', $pathParams[0]['schema']['type']);
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

        $this->assertSame('Get all users', $result['summary']);
    }

    public function testGenerateDescriptionWithPhpDocBody(): void
    {
        $method = new \ReflectionMethod(TestControllerWithDoc::class, 'detail');
        $route = new Route('/api/test/detail');

        $result = $this->describer->describe($method, $route);

        $this->assertSame('List users.', $result['summary']);
        $this->assertSame('Returns all active users.', $result['description']);
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
        $this->assertSame('User payload', $result['requestBody']['description']);
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

    public function testDescribeWithOptionalMapRequestPayloadDtoMarksRequestBodyOptional(): void
    {
        if (!class_exists(MapRequestPayload::class)) {
            $this->markTestSkipped('MapRequestPayload not available.');
        }

        $method = new \ReflectionMethod(TestControllerWithRequestPayload::class, 'optionalCreate');
        $route = new Route('/api/users/optional');

        $result = $this->describer->describe($method, $route);

        $this->assertArrayHasKey('requestBody', $result);
        $this->assertFalse($result['requestBody']['required']);
    }

    public function testDescribeWithMapRequestPayloadArrayWithoutValidDtoDoesNotCreateRequestBody(): void
    {
        if (!class_exists(MapRequestPayload::class)) {
            $this->markTestSkipped('MapRequestPayload not available.');
        }

        $method = new \ReflectionMethod(TestControllerWithRequestPayload::class, 'bulkCreateWithoutValidDto');
        $route = new Route('/api/users/bulk-invalid');

        $result = $this->describer->describe($method, $route);

        $this->assertArrayNotHasKey('requestBody', $result);
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
        $this->assertSame('Search keyword.', $byName['keyword']['description']);
        $this->assertSame('Page number.', $byName['page']['description']);
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
        $this->assertSame('Filters applied as deep object', $param['description']);
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
        $this->assertSame('Avatar image', $content['properties']['file']['description']);
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

    public function testDescribeWithOptionalUploadedFileMarksMultipartBodyOptional(): void
    {
        if (!class_exists(MapUploadedFile::class) || !class_exists(UploadedFile::class)) {
            $this->markTestSkipped('MapUploadedFile or UploadedFile not available.');
        }

        $method = new \ReflectionMethod(TestControllerWithUploads::class, 'uploadOptional');
        $route = new Route('/api/upload-optional');

        $result = $this->describer->describe($method, $route);

        $this->assertArrayHasKey('requestBody', $result);
        $this->assertFalse($result['requestBody']['required']);
        $schema = $result['requestBody']['content']['multipart/form-data']['schema'];
        $this->assertArrayNotHasKey('required', $schema);
    }

    public function testDescribeWithApiResponseCollectionUsesArrayDataSchema(): void
    {
        $method = new \ReflectionMethod(TestControllerWithApiResponses::class, 'collection');
        $route = new Route('/api/users');

        $result = $this->describer->describe($method, $route);

        $data = $result['responses']['200']['content']['application/json']['schema']['properties']['data'];
        $this->assertSame('array', $data['type']);
        $this->assertSame('#/components/schemas/TestDtoForDescriber', $data['items']['$ref']);
    }

    public function testDescribeWithApiResponseInvalidTypeFallsBackToNullableData(): void
    {
        $reflection = new \ReflectionClass(\SymfonySwagger\Attribute\ApiResponse::class);
        /** @var \SymfonySwagger\Attribute\ApiResponse $apiResponse */
        $apiResponse = $reflection->newInstanceWithoutConstructor();
        $this->setReadonlyProperty($apiResponse, 'type', 'MissingDto');
        $this->setReadonlyProperty($apiResponse, 'collection', false);
        $this->setReadonlyProperty($apiResponse, 'file', false);
        $this->setReadonlyProperty($apiResponse, 'fileMediaType', 'application/octet-stream');

        $resolver = \Closure::bind(
            fn (\SymfonySwagger\Attribute\ApiResponse $response): array => $this->buildEnvelopeSchema($response),
            $this->describer,
            OperationDescriber::class,
        );

        $schema = $resolver($apiResponse);
        $data = $schema['properties']['data'];
        $this->assertTrue($data['nullable']);
        $this->assertSame('Response data', $data['description']);
    }

    public function testDescribePathParameterSchemaFallsBackToStringWithoutHints(): void
    {
        $resolver = \Closure::bind(
            fn (\ReflectionMethod $method, Route $route, string $param): array => $this->describePathParameterSchema($method, $route, $param),
            $this->describer,
            OperationDescriber::class,
        );

        $schema = $resolver(new \ReflectionMethod(TestControllerWithPathParameters::class, 'slug'), new Route('/api/users/{missing}'), 'missing');

        $this->assertSame(['type' => 'string'], $schema);
    }

    public function testPathParameterDescriptionFromPhpDoc(): void
    {
        $method = new \ReflectionMethod(TestControllerWithPathParameters::class, 'show');
        $route = new Route('/api/users/{id}');

        $result = $this->describer->describe($method, $route);

        $pathParams = array_values(array_filter($result['parameters'] ?? [], fn ($p) => 'path' === $p['in']));
        $this->assertSame('User identifier', $pathParams[0]['description']);
    }

    public function testBuildFileResponseIncludesDownloadHeaders(): void
    {
        $resolver = \Closure::bind(
            fn (string $mediaType): array => $this->buildFileResponse($mediaType),
            $this->describer,
            OperationDescriber::class,
        );

        $response = $resolver('application/pdf');

        $this->assertSame('File download', $response['200']['description']);
        $this->assertArrayHasKey('Content-Disposition', $response['200']['headers']);
        $this->assertSame('binary', $response['200']['content']['application/pdf']['schema']['format']);
    }

    public function testBuildEnvelopeSchemaUsesNullableFallbackDataWhenResponseMissing(): void
    {
        $resolver = \Closure::bind(
            fn (): array => $this->buildEnvelopeSchema(null),
            $this->describer,
            OperationDescriber::class,
        );

        $schema = $resolver();

        $this->assertSame('object', $schema['type']);
        $this->assertTrue($schema['properties']['data']['nullable']);
    }

    private function setReadonlyProperty(object $object, string $property, mixed $value): void
    {
        $reflectionProperty = new \ReflectionProperty($object, $property);
        $reflectionProperty->setValue($object, $value);
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

    /**
     * List users.
     *
     * Returns all active users.
     */
    public function detail(): array
    {
        return [];
    }
}

class TestDtoForDescriber
{
    /**
     * Display name.
     */
    public string $name;

    /**
     * Email address.
     */
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
    /**
     * Search keyword.
     */
    public string $keyword;

    /**
     * Page number.
     */
    public ?int $page = null;
}

class TestControllerWithQueryString
{
    public function list(#[MapQueryString] QueryFilterDto $filter): array
    {
        return [];
    }

    /**
     * @param QueryFilterDto $filter Filters applied as deep object
     */
    public function search(#[MapQueryString(key: 'filter')] QueryFilterDto $filter): array
    {
        return [];
    }
}

class TestControllerWithRequestPayload
{
    /**
     * @param TestDtoForDescriber $dto User payload
     */
    public function create(#[MapRequestPayload] TestDtoForDescriber $dto): array
    {
        return [];
    }

    public function bulkCreate(#[MapRequestPayload(type: TestDtoForDescriber::class)] array $items): array
    {
        return [];
    }

    public function optionalCreate(#[MapRequestPayload] ?TestDtoForDescriber $dto = null): array
    {
        return [];
    }

    public function bulkCreateWithoutValidDto(#[MapRequestPayload(type: 'MissingDto')] array $items): array
    {
        return [];
    }
}

class TestControllerWithUploads
{
    /**
     * @param UploadedFile $file Avatar image
     */
    public function upload(#[MapUploadedFile(name: 'file')] UploadedFile $file): array
    {
        return [];
    }

    public function uploadMany(#[MapUploadedFile(name: 'files')] array $files): array
    {
        return [];
    }

    public function uploadOptional(#[MapUploadedFile(name: 'file')] ?UploadedFile $file = null): array
    {
        return [];
    }
}

class TestControllerWithPathParameters
{
    /**
     * @param int $id User identifier
     */
    public function show(int $id): array
    {
        return [];
    }

    public function slug(string $slug): array
    {
        return [];
    }
}

class TestControllerWithApiResponses
{
    #[\SymfonySwagger\Attribute\ApiResponse(type: TestDtoForDescriber::class, collection: true)]
    public function collection(): array
    {
        return [];
    }
}

class TestControllerWithSecurity
{
    #[\Symfony\Component\Security\Http\Attribute\IsGranted('ROLE_USER')]
    public function secured(): array
    {
        return [];
    }

    #[\Symfony\Component\Security\Http\Attribute\IsGranted('ROLE_ADMIN', methods: ['POST'])]
    public function writeOnly(): array
    {
        return [];
    }

    #[\Symfony\Component\Security\Http\Attribute\IsGranted('ROLE_ADMIN', methods: 'POST')]
    public function postOnlyStringMethod(): array
    {
        return [];
    }

    #[\Symfony\Component\Security\Http\Attribute\IsGranted('PUBLIC_ACCESS')]
    public function publicAccess(): array
    {
        return [];
    }

    #[\Symfony\Component\Security\Http\Attribute\IsGranted(['PUBLIC_ACCESS'])]
    public function publicAccessArray(): array
    {
        return [];
    }
}

#[\Symfony\Component\Security\Http\Attribute\IsGranted('ROLE_ADMIN')]
class TestClassSecuredController
{
    public function index(): array
    {
        return [];
    }
}
