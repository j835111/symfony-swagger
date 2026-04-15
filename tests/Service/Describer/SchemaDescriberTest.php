<?php

declare(strict_types=1);

namespace SymfonySwagger\Tests\Service\Describer;

use PHPUnit\Framework\TestCase;
use SymfonySwagger\Analyzer\TypeAnalyzer;
use SymfonySwagger\Service\Describer\SchemaDescriber;
use SymfonySwagger\Service\Registry\SchemaRegistry;

/**
 * Test case for SchemaDescriber.
 */
class SchemaDescriberTest extends TestCase
{
    private SchemaDescriber $describer;
    private TypeAnalyzer $typeAnalyzer;
    private SchemaRegistry $schemaRegistry;

    protected function setUp(): void
    {
        $this->schemaRegistry = new SchemaRegistry();
        $this->typeAnalyzer = new TypeAnalyzer();
        $this->describer = new SchemaDescriber($this->typeAnalyzer, $this->schemaRegistry);
    }

    public function testDescribeSimpleClass(): void
    {
        $class = new \ReflectionClass(SimpleTestDto::class);

        $result = $this->describer->describe($class);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('$ref', $result);
        $this->assertStringContainsString('SimpleTestDto', $result['$ref']);
    }

    public function testDescribeReturnsRef(): void
    {
        $class = new \ReflectionClass(SimpleTestDto::class);

        $result = $this->describer->describe($class);

        $this->assertArrayHasKey('$ref', $result);
    }

    public function testSchemaIsRegisteredAfterDescribe(): void
    {
        $class = new \ReflectionClass(SimpleTestDto::class);

        $this->describer->describe($class);

        $this->assertTrue($this->schemaRegistry->has('SimpleTestDto'));
    }

    public function testSchemaHasCorrectStructure(): void
    {
        $class = new \ReflectionClass(SimpleTestDto::class);

        $this->describer->describe($class);

        $schemas = $this->schemaRegistry->getSchemas();
        $schema = $schemas['SimpleTestDto'];

        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
    }

    public function testSchemaRequiredProperties(): void
    {
        $class = new \ReflectionClass(RequiredFieldsDto::class);

        $this->describer->describe($class);

        $schemas = $this->schemaRegistry->getSchemas();
        $schema = $schemas['RequiredFieldsDto'];

        $this->assertArrayHasKey('required', $schema);
        $this->assertContains('name', $schema['required']);
        $this->assertContains('count', $schema['required']);
        $this->assertNotContains('optional', $schema['required']);
        $this->assertNotContains('withDefault', $schema['required']);
        $this->assertNotContains('maybe', $schema['required']);
    }

    public function testDescribeRegistersNestedDtoSchemas(): void
    {
        $class = new \ReflectionClass(NestedParentDto::class);

        $this->describer->describe($class);

        $schemas = $this->schemaRegistry->getSchemas();

        $this->assertArrayHasKey('NestedParentDto', $schemas);
        $this->assertArrayHasKey('NestedChildDto', $schemas);
        $this->assertSame('#/components/schemas/NestedChildDto', $schemas['NestedParentDto']['properties']['child']['$ref']);
    }

    public function testDescribeRegistersNestedArrayDtoSchemas(): void
    {
        $class = new \ReflectionClass(NestedArrayParentDto::class);

        $this->describer->describe($class);

        $schemas = $this->schemaRegistry->getSchemas();

        $this->assertArrayHasKey('NestedArrayParentDto', $schemas);
        $this->assertArrayHasKey('NestedChildDto', $schemas);
        $this->assertSame('#/components/schemas/NestedChildDto', $schemas['NestedArrayParentDto']['properties']['items']['items']['$ref']);
    }

    public function testDescribeReturnsExistingRefWithoutDuplicatingSchema(): void
    {
        $class = new \ReflectionClass(SimpleTestDto::class);

        $first = $this->describer->describe($class);
        $second = $this->describer->describe($class);

        $this->assertSame($first, $second);
        $this->assertCount(1, $this->schemaRegistry->getSchemas());
    }

    public function testDescribeReturnsRefWhenClassIsAlreadyBeingAnalyzed(): void
    {
        $class = new \ReflectionClass(SimpleTestDto::class);
        $this->schemaRegistry->markAnalyzing(SimpleTestDto::class);

        $result = $this->describer->describe($class);

        $this->assertSame('#/components/schemas/SimpleTestDto', $result['$ref']);
    }

    public function testDescribePropertiesSkipsStaticFields(): void
    {
        $resolver = \Closure::bind(
            fn (\ReflectionClass $class): array => $this->describeProperties($class, 0),
            $this->describer,
            SchemaDescriber::class,
        );

        $properties = $resolver(new \ReflectionClass(RequiredFieldsDto::class));

        $this->assertArrayNotHasKey('staticFlag', $properties);
        $this->assertArrayHasKey('name', $properties);
    }

    public function testGetRequiredPropertiesSkipsNullableAndDefaultedFields(): void
    {
        $resolver = \Closure::bind(
            fn (\ReflectionClass $class): array => $this->getRequiredProperties($class),
            $this->describer,
            SchemaDescriber::class,
        );

        $required = $resolver(new \ReflectionClass(RequiredFieldsDto::class));

        $this->assertContains('name', $required);
        $this->assertNotContains('optional', $required);
        $this->assertNotContains('withDefault', $required);
    }

    public function testRegisterNestedSchemaForClassNameSkipsSpecialNonRefTypes(): void
    {
        $resolver = \Closure::bind(
            function (string $className): void {
                $this->registerNestedSchemaForClassName($className, 0);
            },
            $this->describer,
            SchemaDescriber::class,
        );

        $resolver(\DateTime::class);

        $this->assertSame([], $this->schemaRegistry->getSchemas());
    }

    public function testRegisterNestedSchemasForPropertyHandlesUnionDtoTypes(): void
    {
        $resolver = \Closure::bind(
            function (\ReflectionProperty $property): void {
                $this->registerNestedSchemasForProperty($property, 0);
            },
            $this->describer,
            SchemaDescriber::class,
        );

        $resolver(new \ReflectionProperty(UnionNestedParentDto::class, 'child'));

        $schemas = $this->schemaRegistry->getSchemas();
        $this->assertArrayHasKey('NestedChildDto', $schemas);
        $this->assertArrayHasKey('AlternateNestedChildDto', $schemas);
    }

    public function testRegisterNestedSchemaForTypeStringRegistersDirectClassName(): void
    {
        $resolver = \Closure::bind(
            function (string $typeString, ?string $namespace): void {
                $this->registerNestedSchemaForTypeString($typeString, $namespace, 0);
            },
            $this->describer,
            SchemaDescriber::class,
        );

        $resolver(NestedChildDto::class, null);

        $this->assertArrayHasKey('NestedChildDto', $this->schemaRegistry->getSchemas());
    }

    public function testRegisterNestedSchemaForTypeStringSkipsUnknownClassWithoutNamespace(): void
    {
        $resolver = \Closure::bind(
            function (string $typeString, ?string $namespace): void {
                $this->registerNestedSchemaForTypeString($typeString, $namespace, 0);
            },
            $this->describer,
            SchemaDescriber::class,
        );

        $resolver('MissingDto', null);

        $this->assertSame([], $this->schemaRegistry->getSchemas());
    }

    public function testRegisterNestedSchemaForTypeStringResolvesRelativeNamespaceClassName(): void
    {
        $resolver = \Closure::bind(
            function (string $typeString, ?string $namespace): void {
                $this->registerNestedSchemaForTypeString($typeString, $namespace, 0);
            },
            $this->describer,
            SchemaDescriber::class,
        );

        $resolver('NestedChildDto', __NAMESPACE__);

        $this->assertArrayHasKey('NestedChildDto', $this->schemaRegistry->getSchemas());
    }

    public function testRegisterNestedSchemasForPropertySkipsArrayWithoutDocBlockType(): void
    {
        $resolver = \Closure::bind(
            function (\ReflectionProperty $property): void {
                $this->registerNestedSchemasForProperty($property, 0);
            },
            $this->describer,
            SchemaDescriber::class,
        );

        $resolver(new \ReflectionProperty(UndocumentedArrayDto::class, 'items'));

        $this->assertSame([], $this->schemaRegistry->getSchemas());
    }

    public function testSchemaIncludesClassAndPropertyDescriptionsFromPhpDoc(): void
    {
        $class = new \ReflectionClass(DescribedDto::class);

        $this->describer->describe($class);

        $schema = $this->schemaRegistry->getSchemas()['DescribedDto'];

        $this->assertSame("DTO summary.\n\nDTO details.", $schema['description']);
        $this->assertSame('Display name.', $schema['properties']['name']['description']);
        $this->assertSame('Tag labels', $schema['properties']['tags']['description']);
    }

    public function testDescribeWithoutDescriptionOrRequiredKeepsSchemaMinimal(): void
    {
        $class = new \ReflectionClass(AllOptionalDto::class);

        $this->describer->describe($class);

        $schema = $this->schemaRegistry->getSchemas()['AllOptionalDto'];

        $this->assertArrayNotHasKey('description', $schema);
        $this->assertArrayNotHasKey('required', $schema);
    }
}

// Test DTO class
class SimpleTestDto
{
    public string $name;
    public ?string $email = null;
    public int $age;
}

class RequiredFieldsDto
{
    public string $name;
    public int $count;
    public ?string $optional = null;
    public int $withDefault = 1;
    public ?int $maybe;
    public static string $staticFlag = 'x';
}

class NestedParentDto
{
    public NestedChildDto $child;
}

class NestedArrayParentDto
{
    /** @var NestedChildDto[] */
    public array $items;
}

class NestedChildDto
{
    public string $name;
}

class AlternateNestedChildDto
{
    public string $title;
}

class UnionNestedParentDto
{
    public NestedChildDto|AlternateNestedChildDto $child;
}

class UndocumentedArrayDto
{
    public array $items;
}

/**
 * DTO summary.
 *
 * DTO details.
 */
class DescribedDto
{
    /**
     * Display name.
     */
    public string $name;

    /** @var string[] Tag labels */
    public array $tags;
}

class AllOptionalDto
{
    public ?string $optional = null;
    public int $withDefault = 1;
}
