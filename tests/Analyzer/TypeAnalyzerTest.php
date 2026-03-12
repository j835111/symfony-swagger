<?php

declare(strict_types=1);

namespace SymfonySwagger\Tests\Analyzer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use SymfonySwagger\Analyzer\TypeAnalyzer;

class TypeAnalyzerTest extends TestCase
{
    private TypeAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new TypeAnalyzer(maxDepth: 5);
    }

    public function testAnalyzeStringType(): void
    {
        $reflection = new \ReflectionProperty(TestDto::class, 'name');
        $schema = $this->analyzer->analyze($reflection->getType());

        $this->assertSame('string', $schema['type']);
    }

    public function testAnalyzeIntType(): void
    {
        $reflection = new \ReflectionProperty(TestDto::class, 'age');
        $schema = $this->analyzer->analyze($reflection->getType());

        $this->assertSame('integer', $schema['type']);
        $this->assertSame('int32', $schema['format']);
    }

    public function testAnalyzeBoolType(): void
    {
        $reflection = new \ReflectionProperty(TestDto::class, 'active');
        $schema = $this->analyzer->analyze($reflection->getType());

        $this->assertSame('boolean', $schema['type']);
    }

    public function testAnalyzeFloatType(): void
    {
        $reflection = new \ReflectionProperty(TestDto::class, 'price');
        $schema = $this->analyzer->analyze($reflection->getType());

        $this->assertSame('number', $schema['type']);
        $this->assertSame('float', $schema['format']);
    }

    public function testAnalyzeArrayType(): void
    {
        $reflection = new \ReflectionProperty(TestDto::class, 'tags');
        $schema = $this->analyzer->analyze($reflection->getType());

        $this->assertSame('array', $schema['type']);
        $this->assertIsArray($schema['items']);
    }

    public function testAnalyzeNullableType(): void
    {
        $reflection = new \ReflectionProperty(TestDto::class, 'description');
        $schema = $this->analyzer->analyze($reflection->getType());

        $this->assertSame('string', $schema['type']);
        $this->assertTrue($schema['nullable']);
    }

    public function testAnalyzeDateTimeType(): void
    {
        $reflection = new \ReflectionProperty(TestDto::class, 'createdAt');
        $schema = $this->analyzer->analyze($reflection->getType());

        $this->assertSame('string', $schema['type']);
        $this->assertSame('date-time', $schema['format']);
    }

    public function testAnalyzeClassType(): void
    {
        $reflection = new \ReflectionProperty(TestDto::class, 'author');
        $schema = $this->analyzer->analyze($reflection->getType());

        $this->assertArrayHasKey('$ref', $schema);
        $this->assertStringContainsString('AuthorDto', $schema['$ref']);
    }

    public function testAnalyzeUnionType(): void
    {
        $reflection = new \ReflectionProperty(TestDto::class, 'status');
        $schema = $this->analyzer->analyze($reflection->getType());

        // Union type 應該生成 oneOf
        $this->assertTrue(
            isset($schema['oneOf']) || isset($schema['type']),
            'Union type should have oneOf or simplified type',
        );
    }

    public function testAnalyzeEnumType(): void
    {
        $reflection = new \ReflectionProperty(TestDto::class, 'role');
        $schema = $this->analyzer->analyze($reflection->getType());

        $this->assertSame('string', $schema['type']);
        $this->assertIsArray($schema['enum']);
        $this->assertContains('admin', $schema['enum']);
        $this->assertContains('user', $schema['enum']);
    }

    public function testAnalyzeBackedIntEnumType(): void
    {
        $reflection = new \ReflectionProperty(TestDto::class, 'level');
        $schema = $this->analyzer->analyze($reflection->getType());

        $this->assertSame('integer', $schema['type']);
        $this->assertContains(1, $schema['enum']);
        $this->assertContains(2, $schema['enum']);
    }

    public function testAnalyzeUnitEnumType(): void
    {
        $reflection = new \ReflectionProperty(TestDto::class, 'statusFlag');
        $schema = $this->analyzer->analyze($reflection->getType());

        $this->assertSame('string', $schema['type']);
        $this->assertContains('ACTIVE', $schema['enum']);
        $this->assertContains('INACTIVE', $schema['enum']);
    }

    public function testAnalyzeMixedType(): void
    {
        $reflection = new \ReflectionProperty(TestDto::class, 'metadata');
        $schema = $this->analyzer->analyze($reflection->getType());

        $this->assertSame('Mixed type', $schema['description']);
    }

    public function testAnalyzeObjectType(): void
    {
        $reflection = new \ReflectionProperty(TestDto::class, 'payload');
        $schema = $this->analyzer->analyze($reflection->getType());

        $this->assertSame('object', $schema['type']);
    }

    public function testAnalyzeUnionTypeWithNull(): void
    {
        $reflection = new \ReflectionProperty(TestDto::class, 'maybe');
        $schema = $this->analyzer->analyze($reflection->getType());

        $this->assertArrayHasKey('oneOf', $schema);
        $this->assertTrue($schema['nullable']);
    }

    public function testAnalyzeSymfonyInternalClass(): void
    {
        $reflection = new \ReflectionProperty(TestDto::class, 'upload');
        $schema = $this->analyzer->analyze($reflection->getType());

        $this->assertSame('object', $schema['type']);
    }

    public function testExtractFromDocBlockSimpleArray(): void
    {
        $reflection = new \ReflectionProperty(TestDto::class, 'items');
        $elementType = $this->analyzer->extractFromDocBlock($reflection);

        $this->assertSame('string', $elementType);
    }

    public function testAnalyzePropertyWithDocBlock(): void
    {
        $reflection = new \ReflectionProperty(TestDto::class, 'items');
        $schema = $this->analyzer->analyzeProperty($reflection);

        $this->assertSame('array', $schema['type']);
        $this->assertArrayHasKey('items', $schema);
        $this->assertSame('string', $schema['items']['type']);
    }

    public function testAnalyzePropertyWithDocBlockGenericArray(): void
    {
        $reflection = new \ReflectionProperty(TestDto::class, 'authors');
        $schema = $this->analyzer->analyzeProperty($reflection);

        $this->assertSame('array', $schema['type']);
        $this->assertArrayHasKey('items', $schema);
        $this->assertSame('string', $schema['items']['type']);
    }

    public function testMaxDepthProtection(): void
    {
        $analyzer = new TypeAnalyzer(maxDepth: 0);
        $reflection = new \ReflectionProperty(TestDto::class, 'author');
        $schema = $analyzer->analyze($reflection->getType(), depth: 1);

        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('description', $schema);
    }

    public function testCircularReferenceDetection(): void
    {
        $reflection = new \ReflectionClass(AuthorDto::class);
        $context = [AuthorDto::class => true];
        $schema = $this->analyzer->analyze($reflection, depth: 0, context: $context);

        $this->assertArrayHasKey('$ref', $schema);
    }
}

class TestDto
{
    public string $name;
    public int $age;
    public bool $active;
    public float $price;
    public array $tags;
    public ?string $description;
    public \DateTime $createdAt;
    public AuthorDto $author;
    public string|int $status;
    public UserRole $role;
    public AccessLevel $level;
    public StatusFlag $statusFlag;
    public mixed $metadata;
    public object $payload;
    public string|int|null $maybe;
    public UploadedFile $upload;

    /** @var string[] */
    public array $items;

    /** @var array<int, AuthorDto> */
    public array $authors;
}

class AuthorDto
{
    public string $name;
    public string $email;
}

enum UserRole: string
{
    case ADMIN = 'admin';
    case USER = 'user';
    case GUEST = 'guest';
}

enum AccessLevel: int
{
    case LOW = 1;
    case HIGH = 2;
}

enum StatusFlag
{
    case ACTIVE;
    case INACTIVE;
}
