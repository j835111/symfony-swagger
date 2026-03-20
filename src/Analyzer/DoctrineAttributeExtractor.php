<?php

declare(strict_types=1);

namespace SymfonySwagger\Analyzer;

/**
 * DoctrineAttributeExtractor - 解析 Doctrine ORM 屬性.
 *
 * 支援:
 * - @ORM\Column(type="array")
 * - @ORM\Column(type="json")
 * - @ORM\ManyToOne, @ORM\OneToOne
 * - @ORM\ManyToMany, @ORM\OneToMany
 * - @Serializer\Type (JMS Serializer)
 */
class DoctrineAttributeExtractor
{
    /**
     * 從 ReflectionProperty 提取 Doctrine 屬性資訊.
     *
     * @return array{type: string|null, isCollection: bool, targetEntity: string|null, nullable: bool, enum: array|null}|null
     */
    public function extract(\ReflectionProperty $property): ?array
    {
        foreach ($property->getAttributes() as $attribute) {
            $attributeName = $attribute->getName();

            // @ORM\Column
            if ($this->isDoctrineColumn($attributeName)) {
                $args = $attribute->getArguments();

                return [
                    'type' => $args['type'] ?? 'string',
                    'isCollection' => $this->isCollectionType($args['type'] ?? 'string'),
                    'targetEntity' => null,
                    'nullable' => $args['nullable'] ?? false,
                    'enum' => null,
                ];
            }

            // @ORM\ManyToOne
            if ($this->isSubclassOf($attributeName, 'Doctrine\ORM\Mapping\ManyToOne')) {
                $args = $attribute->getArguments();

                return [
                    'type' => 'object',
                    'isCollection' => false,
                    'targetEntity' => $args['targetEntity'] ?? null,
                    'nullable' => true,
                    'enum' => null,
                ];
            }

            // @ORM\OneToMany, @ORM\ManyToMany
            if ($this->isSubclassOf($attributeName, 'Doctrine\ORM\Mapping\OneToMany')
                || $this->isSubclassOf($attributeName, 'Doctrine\ORM\Mapping\ManyToMany')) {
                $args = $attribute->getArguments();

                return [
                    'type' => 'array',
                    'isCollection' => true,
                    'targetEntity' => $args['targetEntity'] ?? null,
                    'nullable' => false,
                    'enum' => null,
                ];
            }

            // @ORM\OneToOne
            if ($this->isSubclassOf($attributeName, 'Doctrine\ORM\Mapping\OneToOne')) {
                $args = $attribute->getArguments();

                return [
                    'type' => 'object',
                    'isCollection' => false,
                    'targetEntity' => $args['targetEntity'] ?? null,
                    'nullable' => true,
                    'enum' => null,
                ];
            }

            // @Serializer\Type (JMS Serializer)
            if ($this->isSerializerType($attributeName)) {
                $args = $attribute->getArguments();
                $type = $args[0] ?? $args['name'] ?? null;
                if (null !== $type) {
                    return $this->parseJmsType($type);
                }
            }
        }

        return null;
    }

    private function isDoctrineColumn(string $className): bool
    {
        return $this->isSubclassOf($className, 'Doctrine\ORM\Mapping\Column');
    }

    private function isSerializerType(string $className): bool
    {
        return 'JMS\Serializer\Annotation\Type' === $className
            || str_ends_with($className, '\\Type');
    }

    private function isCollectionType(string $doctrineType): bool
    {
        return \in_array($doctrineType, ['array', 'json', 'simple_array', 'json_array'], true);
    }

    private function parseJmsType(string $type): array
    {
        $isCollection = false;

        if (str_ends_with($type, '[]')) {
            $isCollection = true;
            $type = substr($type, 0, -2);
        }

        if (str_starts_with($type, 'array<') && str_ends_with($type, '>')) {
            $isCollection = true;
            $inner = substr($type, 6, -1);
            $parts = explode(',', $inner);
            $type = trim(end($parts));
        }

        $builtinTypes = ['string', 'integer', 'int', 'boolean', 'bool', 'float', 'double', 'array', 'DateTime', 'DateTimeInterface'];
        if (\in_array($type, $builtinTypes, true)) {
            return [
                'type' => $this->mapBuiltinType($type),
                'isCollection' => $isCollection,
                'targetEntity' => null,
                'nullable' => false,
                'enum' => null,
            ];
        }

        return [
            'type' => 'object',
            'isCollection' => $isCollection,
            'targetEntity' => $type,
            'nullable' => false,
            'enum' => null,
        ];
    }

    private function mapBuiltinType(string $type): string
    {
        return match ($type) {
            'integer', 'int' => 'integer',
            'boolean', 'bool' => 'boolean',
            'float', 'double' => 'number',
            default => $type,
        };
    }

    private function isSubclassOf(string $className, string $parent): bool
    {
        return is_a($className, $parent, true);
    }

    /**
     * 將 Doctrine 類型轉換為 OpenAPI Schema.
     *
     * @return array<string, mixed>|null
     */
    public function convertToSchema(array $doctrineInfo, ?string $namespace = null): ?array
    {
        $type = $doctrineInfo['type'] ?? 'string';
        $isCollection = $doctrineInfo['isCollection'] ?? false;
        $targetEntity = $doctrineInfo['targetEntity'] ?? null;
        $enum = $doctrineInfo['enum'] ?? null;

        if (null !== $enum) {
            $schema = ['type' => 'string', 'enum' => $enum];
            if ($isCollection) {
                return ['type' => 'array', 'items' => $schema];
            }

            return $schema;
        }

        if (null !== $targetEntity) {
            $schema = ['$ref' => '#/components/schemas/'.$this->resolveClassName($targetEntity, $namespace)];
            if ($isCollection) {
                return ['type' => 'array', 'items' => $schema];
            }

            return $schema;
        }

        $schema = $this->getBuiltinSchema($type);
        if ($isCollection) {
            return ['type' => 'array', 'items' => $schema];
        }

        return $schema;
    }

    private function getBuiltinSchema(string $type): array
    {
        return match ($type) {
            'string', 'text' => ['type' => 'string'],
            'integer', 'smallint', 'bigint' => ['type' => 'integer', 'format' => 'int32'],
            'decimal', 'float' => ['type' => 'number', 'format' => 'float'],
            'boolean' => ['type' => 'boolean'],
            'array', 'json', 'simple_array', 'json_array' => ['type' => 'array'],
            'date', 'datetime', 'datetimetz', 'datetime_immutable', 'time' => ['type' => 'string', 'format' => 'date-time'],
            'object' => ['type' => 'object'],
            default => ['type' => 'string'],
        };
    }

    private function resolveClassName(string $className, ?string $namespace): string
    {
        if (str_starts_with($className, '\\')) {
            return substr($className, 1);
        }

        if (str_contains($className, '\\')) {
            return $className;
        }

        if (null !== $namespace) {
            return $namespace.'\\'.$className;
        }

        return $className;
    }
}
