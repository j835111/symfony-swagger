<?php

declare(strict_types=1);

namespace SymfonySwagger\Analyzer;

use Symfony\Component\PropertyInfo\Extractor\ConstructorExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\Type;

/**
 * TypeInfoExtractor - 整合 Symfony PropertyInfo Component.
 *
 * 提供統一的類型資訊提取介面，支援:
 * - DocBlock annotations (@var, @param)
 * - PHP type hints
 * - Doctrine ORM attributes
 * - Symfony Serializer attributes
 */
class TypeInfoExtractor
{
    private ?PropertyInfoExtractor $extractor = null;

    public function __construct()
    {
        $this->initializeExtractor();
    }

    /**
     * 初始化 PropertyInfo Extractor.
     */
    private function initializeExtractor(): void
    {
        try {
            $phpDocExtractor = new PhpDocExtractor();
            $reflectionExtractor = new ReflectionExtractor();
            $constructorExtractor = new ConstructorExtractor();

            $this->extractor = new PropertyInfoExtractor(
                listExtractors: [$reflectionExtractor],
                typeExtractors: [$phpDocExtractor, $reflectionExtractor, $constructorExtractor],
                accessExtractors: [$reflectionExtractor],
            );
        } catch (\Throwable) {
            $this->extractor = null;
        }
    }

    /**
     * 檢查是否可用.
     */
    public function isAvailable(): bool
    {
        return null !== $this->extractor;
    }

    /**
     * 取得屬性的類型資訊.
     *
     * @return array{types: Type[], description: string|null}
     */
    public function getPropertyInfo(string $class, string $property, ?array $serializerGroups = null): array
    {
        if (null === $this->extractor) {
            return ['types' => [], 'description' => null];
        }

        $context = [];
        if (null !== $serializerGroups && [] !== $serializerGroups) {
            $context['serializer_groups'] = $serializerGroups;
        }

        try {
            $types = $this->extractor->getTypes($class, $property, $context);
            $description = $this->extractor->getShortDescription($class, $property, $context);

            return [
                'types' => $types ?? [],
                'description' => $description,
            ];
        } catch (\Throwable) {
            return ['types' => [], 'description' => null];
        }
    }

    /**
     * 從 PropertyInfo Type 轉換為 OpenAPI Schema.
     *
     * @return array<string, mixed>|null
     */
    public function convertTypeToSchema(Type $type, int $depth = 0, array $context = []): ?array
    {
        if (null !== $type->getClassName()) {
            return [
                '$ref' => '#/components/schemas/'.$type->getClassName(),
            ];
        }

        $builtinType = $type->getBuiltinType();

        if ($type->isCollection()) {
            return $this->handleCollectionType($type, $builtinType, $depth, $context);
        }

        return match ($builtinType) {
            'string' => ['type' => 'string'],
            'int', 'integer' => ['type' => 'integer', 'format' => 'int32'],
            'float' => ['type' => 'number', 'format' => 'float'],
            'bool', 'boolean' => ['type' => 'boolean'],
            'array' => ['type' => 'array'],
            'object' => ['type' => 'object'],
            'iterable' => ['type' => 'array'],
            'mixed' => ['description' => 'Mixed type'],
            'null' => ['type' => 'string', 'nullable' => true],
            default => ['type' => 'string'],
        };
    }

    /**
     * 處理集合類型.
     *
     * @return array<string, mixed>
     */
    private function handleCollectionType(Type $type, string $builtinType, int $depth, array $context): array
    {
        $schema = ['type' => 'array'];
        $hasItems = false;

        $collectionValueTypes = $type->getCollectionValueTypes();
        foreach ($collectionValueTypes as $valueType) {
            $valueSchema = $this->convertTypeToSchema($valueType, $depth, $context);
            if (null !== $valueSchema) {
                $schema['items'] = $valueSchema;
                $hasItems = true;
                break;
            }
        }

        if (!$hasItems) {
            $schema['items'] = ['type' => 'string'];
        }

        $collectionKeyTypes = $type->getCollectionKeyTypes();
        foreach ($collectionKeyTypes as $keyType) {
            if ('integer' !== $keyType->getBuiltinType()) {
                if ($hasItems) {
                    $schema['additionalProperties'] = $schema['items'];
                    unset($schema['items']);
                }
                break;
            }
        }

        return $schema;
    }

    /**
     * 取得屬性的可讀寫資訊.
     */
    public function getPropertyAccess(string $class, string $property): array
    {
        if (null === $this->extractor) {
            return ['readable' => true, 'writable' => true];
        }

        try {
            return [
                'readable' => $this->extractor->isReadable($class, $property) ?? true,
                'writable' => $this->extractor->isWritable($class, $property) ?? true,
            ];
        } catch (\Throwable) {
            return ['readable' => true, 'writable' => true];
        }
    }
}
