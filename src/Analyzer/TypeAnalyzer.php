<?php

declare(strict_types=1);

namespace SymfonySwagger\Analyzer;

use BackedEnum;
use ReflectionClass;

/**
 * TypeAnalyzer - PHP 型別分析器.
 *
 * 負責將 PHP 型別轉換為 OpenAPI Schema 定義。
 * 支援內建型別、類別、Union Types、Nullable Types、Enum 等。
 */
class TypeAnalyzer
{
    /**
     * @param int $maxDepth 最大遞迴深度,防止無限循環
     */
    public function __construct(
        private readonly int $maxDepth = 5,
    ) {
    }

    /**
     * 分析型別並生成 OpenAPI Schema.
     *
     * @param \ReflectionType|\ReflectionClass<object>|null $type 要分析的型別
     * @param int $depth 當前遞迴深度
     * @param array<string, true> $context 已分析的類別名稱(循環引用偵測)
     *
     * @return array<string, mixed> OpenAPI Schema
     */
    public function analyze(
        \ReflectionType|\ReflectionClass|null $type,
        int $depth = 0,
        array $context = [],
    ): array {
        // 超過最大深度,回傳基本 object schema
        if ($depth > $this->maxDepth) {
            return ['type' => 'object', 'description' => 'Max depth exceeded'];
        }

        // 型別為 null,回傳任意型別
        if (null === $type) {
            return ['type' => 'string', 'description' => 'No type hint available'];
        }

        // 如果是 ReflectionClass,直接分析類別
        if ($type instanceof \ReflectionClass) {
            return $this->analyzeClassType($type, $depth, $context);
        }

        // 處理 Union Types (e.g., string|int|null)
        if ($type instanceof \ReflectionUnionType) {
            return $this->analyzeUnionType($type, $depth, $context);
        }

        // 處理 Named Types (e.g., string, int, ClassName)
        if ($type instanceof \ReflectionNamedType) {
            $schema = $type->isBuiltin()
                ? $this->analyzeBuiltinType($type)
                : $this->analyzeClassType(new \ReflectionClass($type->getName()), $depth, $context);

            // 處理 nullable (e.g., ?string)
            if ($type->allowsNull() && !isset($schema['nullable'])) {
                $schema['nullable'] = true;
            }

            return $schema;
        }

        return ['type' => 'string', 'description' => 'Unknown type'];
    }

    /**
     * 分析內建型別 (string, int, float, bool, array).
     */
    private function analyzeBuiltinType(\ReflectionNamedType $type): array
    {
        return match ($type->getName()) {
            'string' => ['type' => 'string'],
            'int' => ['type' => 'integer', 'format' => 'int32'],
            'float' => ['type' => 'number', 'format' => 'float'],
            'bool' => ['type' => 'boolean'],
            'array' => ['type' => 'array', 'items' => ['type' => 'string']], // 預設為 string array
            'object' => ['type' => 'object'],
            'mixed' => ['description' => 'Mixed type'],
            default => ['type' => 'string'],
        };
    }

    /**
     * 分析 Union Types (e.g., string|int).
     *
     * @param array<string, true> $context
     *
     * @return array<string, mixed>
     */
    private function analyzeUnionType(\ReflectionUnionType $type, int $depth, array $context): array
    {
        $types = $type->getTypes();
        $schemas = [];
        $hasNull = false;

        foreach ($types as $subType) {
            // 處理 null type
            if ($subType instanceof \ReflectionNamedType && 'null' === $subType->getName()) {
                $hasNull = true;
                continue;
            }

            $schemas[] = $this->analyze($subType, $depth, $context);
        }

        // 只有一個非 null 型別,直接返回
        if (1 === \count($schemas)) {
            $schema = $schemas[0];
            if ($hasNull) {
                $schema['nullable'] = true;
            }

            return $schema;
        }

        // 多個型別,使用 oneOf
        $schema = ['oneOf' => $schemas];
        if ($hasNull) {
            $schema['nullable'] = true;
        }

        return $schema;
    }

    /**
     * 分析類別型別.
     *
     * @param \ReflectionClass<object> $class
     * @param array<string, true> $context
     *
     * @return array<string, mixed>
     */
    private function analyzeClassType(\ReflectionClass $class, int $depth, array $context): array
    {
        $className = $class->getName();

        // 檢測循環引用
        if (isset($context[$className])) {
            return [
                '$ref' => '#/components/schemas/'.$class->getShortName(),
            ];
        }

        // 處理特殊類別
        $specialSchema = $this->analyzeSpecialClass($class);
        if (null !== $specialSchema) {
            return $specialSchema;
        }

        // 一般 DTO 類別,標記為引用
        return [
            '$ref' => '#/components/schemas/'.$class->getShortName(),
        ];
    }

    /**
     * 分析特殊類別 (DateTime, Enum 等).
     *
     * @param \ReflectionClass<object> $class
     *
     * @return array<string, mixed>|null
     */
    private function analyzeSpecialClass(\ReflectionClass $class): ?array
    {
        $className = $class->getName();

        // DateTime 相關類別
        if ($class->implementsInterface(\DateTimeInterface::class)) {
            return ['type' => 'string', 'format' => 'date-time'];
        }

        // BackedEnum
        if ($class->isEnum() && $class->implementsInterface(\BackedEnum::class)) {
            /** @var class-string<\BackedEnum> $className */
            $cases = $className::cases();
            $values = array_map(fn (\BackedEnum $case) => $case->value, $cases);
            $type = \is_int($values[0] ?? null) ? 'integer' : 'string';

            return [
                'type' => $type,
                'enum' => $values,
            ];
        }

        // 普通 Enum (PHP 8.1+, 無 backing value)
        if ($class->isEnum()) {
            /** @var class-string<\UnitEnum> $className */
            $cases = $className::cases();
            $names = array_map(fn (\UnitEnum $case) => $case->name, $cases);

            return [
                'type' => 'string',
                'enum' => $names,
            ];
        }

        return null;
    }

    /**
     * 從 DocBlock 提取陣列元素型別.
     *
     * 例如: @var int[] 或 @var array<int, string>
     */
    public function extractFromDocBlock(\ReflectionProperty|\ReflectionParameter $reflection): ?string
    {
        $docComment = $reflection->getDocComment();
        if (false === $docComment) {
            return null;
        }

        // 匹配 @var Type[]
        if (preg_match('/@var\s+(\w+)\[\]/', $docComment, $matches)) {
            return $matches[1];
        }

        // 匹配 @var array<Type> 或 @var array<int, Type>
        if (preg_match('/@var\s+array<(?:\w+,\s*)?(\w+)>/', $docComment, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * 分析屬性並考慮 DocBlock.
     *
     * @return array<string, mixed>
     */
    public function analyzeProperty(\ReflectionProperty $property, int $depth = 0, array $context = []): array
    {
        $schema = $this->analyze($property->getType(), $depth, $context);

        // 如果是 array 型別,嘗試從 DocBlock 推導元素型別
        if (isset($schema['type']) && 'array' === $schema['type']) {
            $elementType = $this->extractFromDocBlock($property);
            if (null !== $elementType) {
                $schema['items'] = $this->analyzeTypeString($elementType, $depth + 1, $context);
            }
        }

        return $schema;
    }

    /**
     * 從型別字串分析 Schema (用於 DocBlock).
     *
     * @param array<string, true> $context
     *
     * @return array<string, mixed>
     */
    private function analyzeTypeString(string $typeString, int $depth, array $context): array
    {
        // 基本型別對應
        return match ($typeString) {
            'string' => ['type' => 'string'],
            'int', 'integer' => ['type' => 'integer'],
            'float', 'double' => ['type' => 'number'],
            'bool', 'boolean' => ['type' => 'boolean'],
            'array' => ['type' => 'array'],
            'object' => ['type' => 'object'],
            default => class_exists($typeString)
                ? $this->analyze(new \ReflectionClass($typeString), $depth, $context)
                : ['type' => 'string'],
        };
    }
}
