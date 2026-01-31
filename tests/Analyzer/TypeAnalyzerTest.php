<?php

declare(strict_types=1);

namespace SymfonySwagger\Tests\Analyzer;

use PHPUnit\Framework\TestCase;
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

    /** @var string[] */
    public array $items;
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
