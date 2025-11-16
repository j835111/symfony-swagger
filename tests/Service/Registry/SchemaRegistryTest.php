<?php

declare(strict_types=1);

namespace SymfonySwagger\Tests\Service\Registry;

use PHPUnit\Framework\TestCase;
use SymfonySwagger\Service\Registry\SchemaRegistry;

class SchemaRegistryTest extends TestCase
{
    private SchemaRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new SchemaRegistry();
    }

    public function testRegisterSchema(): void
    {
        $className = 'App\\DTO\\UserDto';
        $schema = ['type' => 'object', 'properties' => []];

        $ref = $this->registry->register($className, $schema);

        $this->assertSame('#/components/schemas/UserDto', $ref);
        $this->assertTrue($this->registry->has($className));
    }

    public function testGetReference(): void
    {
        $className = 'App\\DTO\\PostDto';
        $ref = $this->registry->getReference($className);

        $this->assertSame('#/components/schemas/PostDto', $ref);
    }

    public function testGetSchemas(): void
    {
        $userSchema = ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]];
        $postSchema = ['type' => 'object', 'properties' => ['title' => ['type' => 'string']]];

        $this->registry->register('App\\DTO\\UserDto', $userSchema);
        $this->registry->register('App\\DTO\\PostDto', $postSchema);

        $schemas = $this->registry->getSchemas();

        $this->assertCount(2, $schemas);
        $this->assertArrayHasKey('UserDto', $schemas);
        $this->assertArrayHasKey('PostDto', $schemas);
        $this->assertSame($userSchema, $schemas['UserDto']);
        $this->assertSame($postSchema, $schemas['PostDto']);
    }

    public function testHasReturnsFalseForUnregisteredSchema(): void
    {
        $this->assertFalse($this->registry->has('App\\DTO\\UnknownDto'));
    }

    public function testMarkAnalyzing(): void
    {
        $className = 'App\\DTO\\UserDto';

        $this->assertFalse($this->registry->isAnalyzing($className));

        $this->registry->markAnalyzing($className);
        $this->assertTrue($this->registry->isAnalyzing($className));

        $this->registry->unmarkAnalyzing($className);
        $this->assertFalse($this->registry->isAnalyzing($className));
    }

    public function testClear(): void
    {
        $this->registry->register('App\\DTO\\UserDto', ['type' => 'object']);
        $this->registry->markAnalyzing('App\\DTO\\PostDto');

        $this->assertCount(1, $this->registry->getSchemas());
        $this->assertTrue($this->registry->isAnalyzing('App\\DTO\\PostDto'));

        $this->registry->clear();

        $this->assertCount(0, $this->registry->getSchemas());
        $this->assertFalse($this->registry->isAnalyzing('App\\DTO\\PostDto'));
    }

    public function testGetSchema(): void
    {
        $schema = ['type' => 'object', 'properties' => []];
        $this->registry->register('App\\DTO\\UserDto', $schema);

        $retrievedSchema = $this->registry->getSchema('App\\DTO\\UserDto');

        $this->assertSame($schema, $retrievedSchema);
    }

    public function testGetSchemaReturnsNullForUnregistered(): void
    {
        $schema = $this->registry->getSchema('App\\DTO\\UnknownDto');

        $this->assertNull($schema);
    }

    public function testNameConflictHandling(): void
    {
        // 註冊相同短名稱但不同命名空間的類別
        $schema1 = ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']]];
        $schema2 = ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]];

        $ref1 = $this->registry->register('App\\DTO\\UserDto', $schema1);
        $ref2 = $this->registry->register('App\\Entity\\UserDto', $schema2);

        // 第一個應該是 UserDto
        $this->assertSame('#/components/schemas/UserDto', $ref1);

        // 第二個應該有前綴避免衝突
        $this->assertStringContainsString('UserDto', $ref2);
        $this->assertNotSame($ref1, $ref2);

        // 兩個 Schema 都應該被註冊
        $schemas = $this->registry->getSchemas();
        $this->assertCount(2, $schemas);
    }
}
