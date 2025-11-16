<?php

namespace SymfonySwagger\Tests\Research;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionUnionType;

/**
 * 測試 PHP 型別分析與 OpenAPI Schema 對應
 *
 * 此測試展示如何分析 PHP 型別並轉換為 OpenAPI Schema
 */
class TypeAnalyzerTest extends TestCase
{
    /**
     * 測試基本型別對應
     */
    public function testBasicTypeMapping(): void
    {
        $mappings = [
            'string' => ['type' => 'string'],
            'int' => ['type' => 'integer', 'format' => 'int32'],
            'float' => ['type' => 'number', 'format' => 'float'],
            'bool' => ['type' => 'boolean'],
            'array' => ['type' => 'array'],
        ];

        foreach ($mappings as $phpType => $expectedSchema) {
            $schema = $this->convertTypeToOpenApiSchema($phpType);
            $this->assertEquals($expectedSchema, $schema);
        }
    }

    /**
     * 測試 DateTime 型別對應
     */
    public function testDateTimeTypeMapping(): void
    {
        $schema = $this->convertTypeToOpenApiSchema(\DateTimeInterface::class);

        $this->assertEquals([
            'type' => 'string',
            'format' => 'date-time',
        ], $schema);
    }

    /**
     * 測試類別型別對應 (DTO)
     */
    public function testDtoTypeMapping(): void
    {
        $dtoReflection = new ReflectionClass(ExamplePostDto::class);
        $schema = $this->analyzeDtoClass($dtoReflection);

        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('required', $schema);

        // 檢查屬性
        $this->assertArrayHasKey('title', $schema['properties']);
        $this->assertArrayHasKey('content', $schema['properties']);
        $this->assertArrayHasKey('status', $schema['properties']);

        // 檢查必填欄位
        $this->assertContains('title', $schema['required']);
        $this->assertContains('content', $schema['required']);
    }

    /**
     * 測試 Nullable 型別
     */
    public function testNullableType(): void
    {
        $reflectionClass = new ReflectionClass(ExampleController::class);
        $listMethod = $reflectionClass->getMethod('list');
        $searchParam = $listMethod->getParameters()[2];  // ?string $search

        $type = $searchParam->getType();
        $this->assertInstanceOf(ReflectionNamedType::class, $type);
        $this->assertTrue($type->allowsNull());

        $schema = $this->convertReflectionTypeToSchema($type);
        $this->assertEquals([
            'type' => 'string',
            'nullable' => true,
        ], $schema);
    }

    /**
     * 測試陣列屬性
     */
    public function testArrayProperty(): void
    {
        $dtoReflection = new ReflectionClass(ExamplePostDto::class);
        $tagsProperty = $dtoReflection->getProperty('tags');

        $type = $tagsProperty->getType();
        $this->assertEquals('array', $type->getName());

        // 對於陣列,需要透過 PHPDoc 推導元素型別
        $docComment = $tagsProperty->getDocComment();

        // 由於沒有 PHPDoc,預設為 array of mixed
        $schema = $this->convertReflectionTypeToSchema($type);
        $this->assertEquals([
            'type' => 'array',
            'items' => ['type' => 'string'],  // 假設為 string[]
        ], $schema);
    }

    /**
     * 測試列舉(Enum)型別
     *
     * 注意: status 屬性有 Assert\Choice constraint
     */
    public function testEnumConstraint(): void
    {
        $dtoReflection = new ReflectionClass(ExamplePostDto::class);
        $statusProperty = $dtoReflection->getProperty('status');

        // 讀取 Choice constraint
        $attributes = $statusProperty->getAttributes(\Symfony\Component\Validator\Constraints\Choice::class);
        $this->assertCount(1, $attributes);

        $choice = $attributes[0]->newInstance();
        $choices = $choice->choices;

        $schema = [
            'type' => 'string',
            'enum' => $choices,
            'default' => 'draft',
        ];

        $this->assertEquals(['draft', 'published', 'archived'], $schema['enum']);
    }

    /**
     * 測試驗證規則轉換為 Schema constraints
     */
    public function testValidationToSchemaConstraints(): void
    {
        $dtoReflection = new ReflectionClass(ExamplePostDto::class);
        $titleProperty = $dtoReflection->getProperty('title');

        $constraints = $this->extractConstraints($titleProperty);

        $this->assertArrayHasKey('minLength', $constraints);
        $this->assertArrayHasKey('maxLength', $constraints);
        $this->assertEquals(3, $constraints['minLength']);
        $this->assertEquals(200, $constraints['maxLength']);
    }

    /**
     * 測試預設值處理
     */
    public function testDefaultValue(): void
    {
        $dtoReflection = new ReflectionClass(ExamplePostDto::class);
        $statusProperty = $dtoReflection->getProperty('status');

        // 從屬性預設值取得
        $this->assertTrue($statusProperty->hasDefaultValue());
        $this->assertEquals('draft', $statusProperty->getDefaultValue());

        $schema = [
            'type' => 'string',
            'default' => $statusProperty->getDefaultValue(),
        ];

        $this->assertEquals('draft', $schema['default']);
    }

    // ============ Helper Methods ============

    /**
     * 將 PHP 型別名稱轉換為 OpenAPI Schema
     */
    private function convertTypeToOpenApiSchema(string $phpType): array
    {
        return match($phpType) {
            'string' => ['type' => 'string'],
            'int' => ['type' => 'integer', 'format' => 'int32'],
            'float' => ['type' => 'number', 'format' => 'float'],
            'bool' => ['type' => 'boolean'],
            'array' => ['type' => 'array'],
            \DateTimeInterface::class, \DateTime::class, \DateTimeImmutable::class => [
                'type' => 'string',
                'format' => 'date-time',
            ],
            default => ['$ref' => '#/components/schemas/' . basename(str_replace('\\', '/', $phpType))],
        };
    }

    /**
     * 將 ReflectionType 轉換為 OpenAPI Schema
     */
    private function convertReflectionTypeToSchema(\ReflectionType $type): array
    {
        if ($type instanceof ReflectionNamedType) {
            $schema = $this->convertTypeToOpenApiSchema($type->getName());

            if ($type->allowsNull() && !isset($schema['$ref'])) {
                $schema['nullable'] = true;
            }

            return $schema;
        }

        if ($type instanceof ReflectionUnionType) {
            $types = [];
            foreach ($type->getTypes() as $unionType) {
                $types[] = $this->convertReflectionTypeToSchema($unionType);
            }

            return ['oneOf' => $types];
        }

        return ['type' => 'mixed'];
    }

    /**
     * 分析 DTO 類別並生成完整 Schema
     */
    private function analyzeDtoClass(ReflectionClass $class): array
    {
        $properties = [];
        $required = [];

        foreach ($class->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $propertyName = $property->getName();
            $type = $property->getType();

            if (!$type) {
                continue;
            }

            $propertySchema = $this->convertReflectionTypeToSchema($type);

            // 檢查預設值
            if ($property->hasDefaultValue()) {
                $propertySchema['default'] = $property->getDefaultValue();
            }

            // 從驗證 Attributes 擷取 constraints
            $constraints = $this->extractConstraints($property);
            $propertySchema = array_merge($propertySchema, $constraints);

            $properties[$propertyName] = $propertySchema;

            // 檢查是否為必填(沒有預設值且不允許 null)
            if (!$property->hasDefaultValue() && !($type instanceof ReflectionNamedType && $type->allowsNull())) {
                // 進一步檢查是否有 NotBlank constraint
                $notBlankAttrs = $property->getAttributes(\Symfony\Component\Validator\Constraints\NotBlank::class);
                if (count($notBlankAttrs) > 0) {
                    $required[] = $propertyName;
                }
            }
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
        ];
    }

    /**
     * 從屬性的驗證 Attributes 擷取 constraints
     */
    private function extractConstraints(\ReflectionProperty $property): array
    {
        $constraints = [];

        // Length constraint
        $lengthAttrs = $property->getAttributes(\Symfony\Component\Validator\Constraints\Length::class);
        if (count($lengthAttrs) > 0) {
            $length = $lengthAttrs[0]->newInstance();
            if ($length->min !== null) {
                $constraints['minLength'] = $length->min;
            }
            if ($length->max !== null) {
                $constraints['maxLength'] = $length->max;
            }
        }

        // Range constraint
        $rangeAttrs = $property->getAttributes(\Symfony\Component\Validator\Constraints\Range::class);
        if (count($rangeAttrs) > 0) {
            $range = $rangeAttrs[0]->newInstance();
            if ($range->min !== null) {
                $constraints['minimum'] = $range->min;
            }
            if ($range->max !== null) {
                $constraints['maximum'] = $range->max;
            }
        }

        // Choice constraint (enum)
        $choiceAttrs = $property->getAttributes(\Symfony\Component\Validator\Constraints\Choice::class);
        if (count($choiceAttrs) > 0) {
            $choice = $choiceAttrs[0]->newInstance();
            if ($choice->choices) {
                $constraints['enum'] = $choice->choices;
            }
        }

        return $constraints;
    }
}
