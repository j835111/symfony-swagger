<?php

declare(strict_types=1);

namespace SymfonySwagger\Service\Describer;

use SymfonySwagger\Analyzer\TypeAnalyzer;
use SymfonySwagger\Service\Registry\SchemaRegistry;

/**
 * SchemaDescriber - Schema 描述器.
 *
 * 負責分析 DTO 類別並生成 OpenAPI Schema 定義。
 */
class SchemaDescriber
{
    public function __construct(
        private readonly TypeAnalyzer $typeAnalyzer,
        private readonly SchemaRegistry $schemaRegistry,
    ) {
    }

    /**
     * 描述一個類別並生成 Schema.
     *
     * @param \ReflectionClass<object> $class
     *
     * @return array<string, mixed>
     */
    public function describe(\ReflectionClass $class, int $depth = 0): array
    {
        $className = $class->getName();

        // 檢查循環引用
        if ($this->schemaRegistry->isAnalyzing($className)) {
            return ['$ref' => $this->schemaRegistry->getReference($className)];
        }

        // 檢查是否已註冊
        if ($this->schemaRegistry->has($className)) {
            return ['$ref' => $this->schemaRegistry->getReference($className)];
        }

        // 標記為正在分析
        $this->schemaRegistry->markAnalyzing($className);

        // 分析屬性
        $properties = $this->describeProperties($class, $depth);
        $required = $this->getRequiredProperties($class);

        $schema = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if (!empty($required)) {
            $schema['required'] = $required;
        }

        // 註冊 Schema
        $this->schemaRegistry->register($className, $schema);

        // 取消分析標記
        $this->schemaRegistry->unmarkAnalyzing($className);

        return ['$ref' => $this->schemaRegistry->getReference($className)];
    }

    /**
     * 描述類別的所有屬性.
     *
     * @param \ReflectionClass<object> $class
     *
     * @return array<string, array<string, mixed>>
     */
    private function describeProperties(\ReflectionClass $class, int $depth): array
    {
        $properties = [];

        foreach ($class->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            // 跳過靜態屬性
            if ($property->isStatic()) {
                continue;
            }

            $propertyName = $property->getName();
            $properties[$propertyName] = $this->typeAnalyzer->analyzeProperty($property, $depth + 1, []);
        }

        return $properties;
    }

    /**
     * 取得必填屬性列表.
     *
     * @param \ReflectionClass<object> $class
     *
     * @return list<string>
     */
    private function getRequiredProperties(\ReflectionClass $class): array
    {
        $required = [];

        foreach ($class->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $type = $property->getType();

            // 如果型別不允許 null 且沒有預設值,則為必填
            if (null !== $type && !$type->allowsNull() && !$property->hasDefaultValue()) {
                $required[] = $property->getName();
            }
        }

        return $required;
    }
}
