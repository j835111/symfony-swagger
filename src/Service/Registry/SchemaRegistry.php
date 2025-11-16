<?php

declare(strict_types=1);

namespace SymfonySwagger\Service\Registry;

/**
 * SchemaRegistry - OpenAPI Schema 註冊器.
 *
 * 負責管理和追蹤所有 OpenAPI Schema 定義,避免重複定義和循環引用。
 */
class SchemaRegistry
{
    /**
     * @var array<string, array<string, mixed>> 已註冊的 Schemas
     */
    private array $schemas = [];

    /**
     * @var array<string, true> 正在分析中的類別(用於循環引用偵測)
     */
    private array $analyzing = [];

    /**
     * 註冊一個 Schema.
     *
     * @param string $className 完整的類別名稱
     * @param array<string, mixed> $schema OpenAPI Schema 定義
     * @return string $ref 路徑,例如 "#/components/schemas/UserDto"
     */
    public function register(string $className, array $schema): string
    {
        $schemaName = $this->getSchemaName($className);
        $this->schemas[$schemaName] = $schema;

        return $this->getReference($className);
    }

    /**
     * 檢查 Schema 是否已註冊.
     */
    public function has(string $className): bool
    {
        $schemaName = $this->getSchemaName($className);
        return isset($this->schemas[$schemaName]);
    }

    /**
     * 取得 Schema 的 $ref 路徑.
     *
     * @param string $className 完整的類別名稱
     * @return string $ref 路徑
     */
    public function getReference(string $className): string
    {
        $schemaName = $this->getSchemaName($className);
        return "#/components/schemas/{$schemaName}";
    }

    /**
     * 取得所有已註冊的 Schemas.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getSchemas(): array
    {
        return $this->schemas;
    }

    /**
     * 檢查類別是否正在分析中(循環引用偵測).
     */
    public function isAnalyzing(string $className): bool
    {
        return isset($this->analyzing[$className]);
    }

    /**
     * 標記類別為正在分析中.
     */
    public function markAnalyzing(string $className): void
    {
        $this->analyzing[$className] = true;
    }

    /**
     * 取消分析中標記.
     */
    public function unmarkAnalyzing(string $className): void
    {
        unset($this->analyzing[$className]);
    }

    /**
     * 清空所有註冊的 Schemas.
     */
    public function clear(): void
    {
        $this->schemas = [];
        $this->analyzing = [];
        $this->classNameMap = [];
    }

    /**
     * @var array<string, string> className => schemaName mapping
     */
    private array $classNameMap = [];

    /**
     * 從完整類別名稱取得 Schema 名稱.
     *
     * 例如: App\DTO\UserDto -> UserDto
     *
     * @param string $className 完整的類別名稱
     * @return string Schema 名稱
     */
    private function getSchemaName(string $className): string
    {
        // 如果已經有映射,直接返回
        if (isset($this->classNameMap[$className])) {
            return $this->classNameMap[$className];
        }

        // 取得類別的短名稱
        $parts = explode('\\', $className);
        $shortName = end($parts);

        // 檢查是否有其他類別已使用這個短名稱
        $isNameTaken = false;
        foreach ($this->classNameMap as $existingClass => $existingSchemaName) {
            if ($existingSchemaName === $shortName && $existingClass !== $className) {
                $isNameTaken = true;
                break;
            }
        }

        // 如果名稱已被使用,加上命名空間前綴
        if ($isNameTaken) {
            if (count($parts) > 1) {
                $prefix = $parts[count($parts) - 2];
                $candidateName = $prefix . $shortName;

                // 如果加上前綴後仍衝突,使用完整名稱(移除反斜線)
                foreach ($this->classNameMap as $existingSchemaName) {
                    if ($existingSchemaName === $candidateName) {
                        $schemaName = str_replace('\\', '', $className);
                        $this->classNameMap[$className] = $schemaName;
                        return $schemaName;
                    }
                }

                $this->classNameMap[$className] = $candidateName;
                return $candidateName;
            }
        }

        $this->classNameMap[$className] = $shortName;
        return $shortName;
    }

    /**
     * 取得 Schema(如果存在).
     *
     * @return array<string, mixed>|null
     */
    public function getSchema(string $className): ?array
    {
        $schemaName = $this->getSchemaName($className);
        return $this->schemas[$schemaName] ?? null;
    }
}
