<?php

declare(strict_types=1);

namespace SymfonySwagger\Analyzer;

use Symfony\Component\PropertyInfo\Type as PropertyInfoType;

/**
 * TypeAnalyzer - PHP 型別分析器.
 *
 * 負責將 PHP 型別轉換為 OpenAPI Schema 定義。
 * 支援:
 * - 內建型別、類別、Union Types、Nullable Types、Enum
 * - DocBlock annotations
 * - Symfony PropertyInfo
 * - Doctrine ORM attributes
 * - Symfony Serializer groups
 */
class TypeAnalyzer
{
    private TypeInfoExtractor $typeInfoExtractor;
    private DoctrineAttributeExtractor $doctrineExtractor;

    /**
     * @param int $maxDepth 最大遞迴深度,防止無限循環
     */
    public function __construct(
        private readonly int $maxDepth = 5,
        private readonly ?array $serializerGroups = null,
    ) {
        $this->typeInfoExtractor = new TypeInfoExtractor();
        $this->doctrineExtractor = new DoctrineAttributeExtractor();
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
            'array' => ['type' => 'array'],
            'iterable' => ['type' => 'array'],
            'object' => ['type' => 'object'],
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
            if ($subType instanceof \ReflectionNamedType && 'null' === $subType->getName()) {
                $hasNull = true;
                continue;
            }

            $schemas[] = $this->analyze($subType, $depth, $context);
        }

        if (1 === \count($schemas)) {
            $schema = $schemas[0];
            if ($hasNull) {
                $schema['nullable'] = true;
            }

            return $schema;
        }

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

        if (isset($context[$className])) {
            return [
                '$ref' => '#/components/schemas/'.$class->getShortName(),
            ];
        }

        $specialSchema = $this->analyzeSpecialClass($class);
        if (null !== $specialSchema) {
            return $specialSchema;
        }

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

        if ($class->implementsInterface(\DateTimeInterface::class)) {
            return ['type' => 'string', 'format' => 'date-time'];
        }

        if ($this->isSymfonyInternalClass($className)) {
            return ['type' => 'object'];
        }

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
     * 檢查是否為 Symfony 內部類別.
     *
     * @param string $className 完整類別名稱
     */
    private function isSymfonyInternalClass(string $className): bool
    {
        $internalClasses = [
            'Symfony\Component\HttpFoundation\ResponseHeaderBag',
            'Symfony\Component\HttpFoundation\Response',
            'Symfony\Component\HttpFoundation\JsonResponse',
            'Symfony\Component\HttpFoundation\Request',
            'Symfony\Component\HttpFoundation\Cookie',
            'Symfony\Component\HttpFoundation\FileBag',
            'Symfony\Component\HttpFoundation\HeaderBag',
            'Symfony\Component\HttpFoundation\ServerBag',
            'Symfony\Component\HttpFoundation\ParameterBag',
            'Symfony\Component\HttpFoundation\File\UploadedFile',
        ];

        foreach ($internalClasses as $internalClass) {
            if ($className === $internalClass || is_subclass_of($className, $internalClass)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 從 DocBlock 提取陣列元素型別.
     *
     * @return array{0: string, 1: string|null}|null [元素型別, 鍵型別] 或 null
     */
    public function extractFromDocBlock(\ReflectionProperty|\ReflectionParameter $reflection): ?array
    {
        $docComment = $reflection->getDocComment();
        if (false === $docComment) {
            return null;
        }

        // @var string[]
        if (preg_match('#@var\s+([\w]+)\[\]#', $docComment, $matches)) {
            return [$matches[1], null];
        }

        // @var list<int>
        if (preg_match('#@var\s+list<([\w]+)>#', $docComment, $matches)) {
            return [$matches[1], null];
        }

        // @var array-key[]
        if (preg_match('#@var\s+array-key\[\]#', $docComment, $matches)) {
            return ['mixed', null];
        }

        // @var array<int, AuthorDto> - with key type
        if (preg_match('#@var\s+array<[\w]+,\s*([\w]+)>#', $docComment, $matches)) {
            return [$matches[1], 'string'];
        }

        // @var array<string> - simple generic
        if (preg_match('#@var\s+array<([\w]+)>#', $docComment, $matches)) {
            return [$matches[1], null];
        }

        return null;
    }

    public function analyzeProperty(\ReflectionProperty $property, int $depth = 0, array $context = []): array
    {
        $schema = $this->analyze($property->getType(), $depth, $context);
        $className = $property->getDeclaringClass()->getName();
        $propertyName = $property->getName();

        if (isset($schema['type']) && 'array' === $schema['type']) {
            $extracted = $this->extractArrayElementType($property, $context);
            if (null !== $extracted) {
                if (isset($extracted['additionalProperties'])) {
                    $schema['additionalProperties'] = $extracted['additionalProperties'];
                } else {
                    $schema['items'] = $extracted;
                }
            } else {
                throw new \LogicException(\sprintf('Property "%s:%s" is an array, but its items type is not specified. Please add a @var annotation, e.g., @var string[], @var array<UserDto>, or @var list<int>.', $className, $propertyName));
            }
        }

        return $schema;
    }

    /**
     * 提取陣列元素類型（從多個來源）.
     *
     * @return array<string, mixed>|null
     */
    private function extractArrayElementType(\ReflectionProperty $property, array $context): ?array
    {
        $className = $property->getDeclaringClass()->getName();
        $propertyName = $property->getName();
        $namespace = $property->getDeclaringClass()->getNamespaceName();

        // 1. 從 DocBlock 提取
        $docBlockType = $this->extractFromDocBlock($property);
        if (null !== $docBlockType) {
            [$elementType, $keyType] = $docBlockType;

            if (null !== $keyType) {
                return [
                    'type' => 'array',
                    'additionalProperties' => $this->analyzeTypeString($elementType, $context, $namespace),
                ];
            }

            return $this->analyzeTypeString($elementType, $context, $namespace);
        }

        // 2. 從 Doctrine Attribute 提取
        $doctrineInfo = $this->doctrineExtractor->extract($property);
        if (null !== $doctrineInfo) {
            $doctrineSchema = $this->doctrineExtractor->convertToSchema($doctrineInfo, $namespace);
            if (null !== $doctrineSchema) {
                return $doctrineSchema;
            }
        }

        // 3. 從 PropertyInfo 提取
        if ($this->typeInfoExtractor->isAvailable()) {
            $propertyInfo = $this->typeInfoExtractor->getPropertyInfo(
                $className,
                $propertyName,
                $this->serializerGroups,
            );

            foreach ($propertyInfo['types'] as $type) {
                if ($type instanceof PropertyInfoType && $type->isCollection()) {
                    $collectionValueTypes = $type->getCollectionValueTypes();
                    foreach ($collectionValueTypes as $valueType) {
                        $schema = $this->typeInfoExtractor->convertTypeToSchema($valueType, 0, $context);
                        if (null !== $schema) {
                            return $schema;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * 從型別字串分析 Schema (用於 DocBlock).
     *
     * @param array<string, true> $context
     *
     * @return array<string, mixed>
     */
    private function analyzeTypeString(string $typeString, array $context, ?string $namespace = null): array
    {
        return match ($typeString) {
            'string' => ['type' => 'string'],
            'int', 'integer' => ['type' => 'integer'],
            'float', 'double' => ['type' => 'number'],
            'bool', 'boolean' => ['type' => 'boolean'],
            'array' => ['type' => 'array'],
            'object' => ['type' => 'object'],
            'mixed' => ['description' => 'Mixed type'],
            'null' => ['type' => 'string', 'nullable' => true],
            'resource' => ['type' => 'string', 'description' => 'Resource'],
            default => $this->resolveClassAndAnalyze($typeString, $context, $namespace),
        };
    }

    /**
     * 解析類別名稱並分析.
     *
     * @param array<string, true> $context
     *
     * @return array<string, mixed>
     */
    private function resolveClassAndAnalyze(string $typeString, array $context, ?string $namespace): array
    {
        if (class_exists($typeString)) {
            return $this->analyze(new \ReflectionClass($typeString), 0, $context);
        }

        if (null !== $namespace) {
            $fullClassName = $namespace.'\\'.$typeString;
            if (class_exists($fullClassName)) {
                return $this->analyze(new \ReflectionClass($fullClassName), 0, $context);
            }
        }

        return ['type' => 'string'];
    }
}
