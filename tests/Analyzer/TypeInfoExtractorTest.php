<?php

declare(strict_types=1);

namespace SymfonySwagger\Tests\Analyzer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
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
        $this->assertSame('Item labels', $result['description']);
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
        $type = $this->createSyntheticType('mixed');
        $schema = $this->extractor->convertTypeToSchema($type);

        $this->assertSame(['description' => 'Mixed type'], $schema);
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

    public function testConvertTypeToSchemaCollectionWithStringKeysUsesAdditionalProperties(): void
    {
        $type = new Type(
            'array',
            false,
            null,
            true,
            [new Type('string')],
            [new Type('int')],
        );
        $schema = $this->extractor->convertTypeToSchema($type);

        $this->assertSame('array', $schema['type']);
        $this->assertArrayHasKey('additionalProperties', $schema);
        $this->assertSame('integer', $schema['additionalProperties']['type']);
    }

    public function testConvertTypeToSchemaCollectionWithoutItemsFallsBackToStringItems(): void
    {
        $type = new Type('array', false, null, true, [new Type('int')], []);
        $schema = $this->extractor->convertTypeToSchema($type);

        $this->assertSame('array', $schema['type']);
        $this->assertSame(['type' => 'string'], $schema['items']);
    }

    public function testGetPropertyInfoReturnsFallbackWhenExtractorUnavailable(): void
    {
        $this->disableInternalExtractor();

        $result = $this->extractor->getPropertyInfo(TIETestDto::class, 'name');

        $this->assertSame([], $result['types']);
        $this->assertNull($result['description']);
    }

    public function testGetPropertyInfoReturnsFallbackWhenExtractorThrows(): void
    {
        $this->replaceInternalExtractor(new ThrowingPropertyInfoExtractor());

        $result = $this->extractor->getPropertyInfo(TIETestDto::class, 'name');

        $this->assertSame([], $result['types']);
        $this->assertNull($result['description']);
    }

    public function testGetPropertyAccessReturnsFallbackWhenExtractorUnavailable(): void
    {
        $this->disableInternalExtractor();

        $access = $this->extractor->getPropertyAccess(TIETestDto::class, 'name');

        $this->assertSame(['readable' => true, 'writable' => true], $access);
    }

    public function testGetPropertyAccessReturnsFallbackWhenExtractorThrows(): void
    {
        $this->replaceInternalExtractor(new ThrowingPropertyInfoExtractor());

        $access = $this->extractor->getPropertyAccess(TIETestDto::class, 'name');

        $this->assertSame(['readable' => true, 'writable' => true], $access);
    }

    public function testGetPropertyInfoReturnsEmptyTypesWhenExtractorReturnsNullTypes(): void
    {
        $this->replaceInternalExtractor(new NullTypesPropertyInfoExtractor());

        $result = $this->extractor->getPropertyInfo(TIETestDto::class, 'name');

        $this->assertSame([], $result['types']);
        $this->assertSame('Synthetic description', $result['description']);
    }

    public function testGetPropertyAccessFallsBackWhenExtractorReturnsNullFlags(): void
    {
        $this->replaceInternalExtractor(new NullAccessPropertyInfoExtractor());

        $access = $this->extractor->getPropertyAccess(TIETestDto::class, 'name');

        $this->assertSame(['readable' => true, 'writable' => true], $access);
    }

    public function testConvertTypeToSchemaFallsBackToStringForUnknownBuiltinType(): void
    {
        $type = $this->createSyntheticType('custom');
        $schema = $this->extractor->convertTypeToSchema($type);

        $this->assertSame(['type' => 'string'], $schema);
    }

    private function disableInternalExtractor(): void
    {
        $this->replaceInternalExtractor(null);
    }

    private function replaceInternalExtractor(?PropertyInfoExtractorInterface $extractor): void
    {
        $property = new \ReflectionProperty(TypeInfoExtractor::class, 'extractor');
        $property->setValue($this->extractor, $extractor);
    }

    private function createSyntheticType(string $builtinType): Type
    {
        $reflection = new \ReflectionClass(Type::class);
        /** @var Type $type */
        $type = $reflection->newInstanceWithoutConstructor();

        foreach ([
            'builtinType' => $builtinType,
            'nullable' => false,
            'class' => null,
            'collection' => false,
            'collectionKeyType' => [],
            'collectionValueType' => [],
        ] as $propertyName => $value) {
            $property = $reflection->getProperty($propertyName);
            $property->setValue($type, $value);
        }

        return $type;
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

    /** @var string[] Item labels */
    public array $items;

    public string $publicProperty;
}

class AuthorDtoTypeInfo
{
    public string $name;
    public string $email;
}

class ThrowingPropertyInfoExtractor implements PropertyInfoExtractorInterface
{
    public function getTypes(string $class, string $property, array $context = []): ?array
    {
        throw new \RuntimeException('boom');
    }

    public function getProperties(string $class, array $context = []): ?array
    {
        throw new \RuntimeException('boom');
    }

    public function getShortDescription(string $class, string $property, array $context = []): ?string
    {
        throw new \RuntimeException('boom');
    }

    public function getLongDescription(string $class, string $property, array $context = []): ?string
    {
        throw new \RuntimeException('boom');
    }

    public function isReadable(string $class, string $property, array $context = []): ?bool
    {
        throw new \RuntimeException('boom');
    }

    public function isWritable(string $class, string $property, array $context = []): ?bool
    {
        throw new \RuntimeException('boom');
    }
}

class NullTypesPropertyInfoExtractor implements PropertyInfoExtractorInterface
{
    public function getTypes(string $class, string $property, array $context = []): ?array
    {
        return null;
    }

    public function getProperties(string $class, array $context = []): ?array
    {
        return [];
    }

    public function getShortDescription(string $class, string $property, array $context = []): ?string
    {
        return 'Synthetic description';
    }

    public function getLongDescription(string $class, string $property, array $context = []): ?string
    {
        return null;
    }

    public function isReadable(string $class, string $property, array $context = []): ?bool
    {
        return true;
    }

    public function isWritable(string $class, string $property, array $context = []): ?bool
    {
        return true;
    }
}

class NullAccessPropertyInfoExtractor implements PropertyInfoExtractorInterface
{
    public function getTypes(string $class, string $property, array $context = []): ?array
    {
        return [];
    }

    public function getProperties(string $class, array $context = []): ?array
    {
        return [];
    }

    public function getShortDescription(string $class, string $property, array $context = []): ?string
    {
        return null;
    }

    public function getLongDescription(string $class, string $property, array $context = []): ?string
    {
        return null;
    }

    public function isReadable(string $class, string $property, array $context = []): ?bool
    {
        return null;
    }

    public function isWritable(string $class, string $property, array $context = []): ?bool
    {
        return null;
    }
}
