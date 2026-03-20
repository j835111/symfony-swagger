<?php

declare(strict_types=1);

namespace SymfonySwagger\Tests\Analyzer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Type;
use SymfonySwagger\Analyzer\TypeInfoExtractor;

class TypeInfoExtractorTest extends TestCase
{
    private TypeInfoExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new TypeInfoExtractor();
    }

    public function testIsAvailable(): void
    {
        $this->assertTrue($this->extractor->isAvailable());
    }

    public function testGetPropertyInfoBasic(): void
    {
        $result = $this->extractor->getPropertyInfo(TIETestDto::class, 'name');

        $this->assertArrayHasKey('types', $result);
        $this->assertArrayHasKey('description', $result);
    }

    public function testGetPropertyInfoWithDocBlock(): void
    {
        $result = $this->extractor->getPropertyInfo(TIETestDto::class, 'items');

        $this->assertArrayHasKey('types', $result);
        $types = $result['types'];
        $this->assertNotEmpty($types);
    }

    public function testGetPropertyInfoWithSerializerGroups(): void
    {
        $result = $this->extractor->getPropertyInfo(TIETestDto::class, 'name', ['read', 'write']);

        $this->assertArrayHasKey('types', $result);
    }

    public function testConvertTypeToSchemaString(): void
    {
        $type = new Type('string');
        $schema = $this->extractor->convertTypeToSchema($type);

        $this->assertSame(['type' => 'string'], $schema);
    }

    public function testConvertTypeToSchemaInt(): void
    {
        $type = new Type('int');
        $schema = $this->extractor->convertTypeToSchema($type);

        $this->assertSame(['type' => 'integer', 'format' => 'int32'], $schema);
    }

    public function testConvertTypeToSchemaFloat(): void
    {
        $type = new Type('float');
        $schema = $this->extractor->convertTypeToSchema($type);

        $this->assertSame(['type' => 'number', 'format' => 'float'], $schema);
    }

    public function testConvertTypeToSchemaBool(): void
    {
        $type = new Type('bool');
        $schema = $this->extractor->convertTypeToSchema($type);

        $this->assertSame(['type' => 'boolean'], $schema);
    }

    public function testConvertTypeToSchemaArray(): void
    {
        $type = new Type('array');
        $schema = $this->extractor->convertTypeToSchema($type);

        $this->assertSame(['type' => 'array'], $schema);
    }

    public function testConvertTypeToSchemaObject(): void
    {
        $type = new Type('object');
        $schema = $this->extractor->convertTypeToSchema($type);

        $this->assertSame(['type' => 'object'], $schema);
    }

    public function testConvertTypeToSchemaMixed(): void
    {
        // Test with 'object' type that isn't a known class - should return object schema
        $type = new Type('object');
        $schema = $this->extractor->convertTypeToSchema($type);

        $this->assertSame(['type' => 'object'], $schema);
    }

    public function testConvertTypeToSchemaNull(): void
    {
        $type = new Type('null', true);
        $schema = $this->extractor->convertTypeToSchema($type);

        $this->assertSame(['type' => 'string', 'nullable' => true], $schema);
    }

    public function testConvertTypeToSchemaIterable(): void
    {
        $type = new Type('iterable');
        $schema = $this->extractor->convertTypeToSchema($type);

        $this->assertSame(['type' => 'array'], $schema);
    }

    public function testConvertTypeToSchemaCollection(): void
    {
        $type = new Type('array', false, null, true);
        $schema = $this->extractor->convertTypeToSchema($type);

        $this->assertSame('array', $schema['type']);
    }

    public function testConvertTypeToSchemaClassReference(): void
    {
        $type = new Type('object', false, AuthorDtoTypeInfo::class);
        $schema = $this->extractor->convertTypeToSchema($type);

        $this->assertArrayHasKey('$ref', $schema);
        $this->assertStringContainsString('AuthorDtoTypeInfo', $schema['$ref']);
    }

    public function testGetPropertyAccess(): void
    {
        $access = $this->extractor->getPropertyAccess(TIETestDto::class, 'name');

        $this->assertArrayHasKey('readable', $access);
        $this->assertArrayHasKey('writable', $access);
    }

    public function testGetPropertyAccessFromPropertyWithNoGetterSetter(): void
    {
        $access = $this->extractor->getPropertyAccess(TIETestDto::class, 'publicProperty');

        $this->assertArrayHasKey('readable', $access);
        $this->assertArrayHasKey('writable', $access);
    }
}

class TIETestDto
{
    public string $name;
    public int $age;
    public bool $active;
    public float $price;
    public array $tags;
    public ?string $description;
    public \DateTime $createdAt;
    public AuthorDtoTypeInfo $author;

    /** @var string[] */
    public array $items;

    public string $publicProperty;
}

class AuthorDtoTypeInfo
{
    public string $name;
    public string $email;
}
