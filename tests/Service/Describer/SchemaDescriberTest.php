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
