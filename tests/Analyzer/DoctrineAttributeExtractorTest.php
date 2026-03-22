<?php

declare(strict_types=1);

namespace SymfonySwagger\Tests\Analyzer;

use PHPUnit\Framework\TestCase;
use SymfonySwagger\Analyzer\DoctrineAttributeExtractor;

class DoctrineAttributeExtractorTest extends TestCase
{
    private DoctrineAttributeExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new DoctrineAttributeExtractor();
    }

    public function testExtractFromDocBlockProperty(): void
    {
        $reflection = new \ReflectionProperty(DocBlockTestDto::class, 'tags');
        $result = $this->extractor->extract($reflection);

        // No Doctrine attributes, should return null
        $this->assertNull($result);
    }

    public function testExtractFromDoctrineColumn(): void
    {
        $reflection = new \ReflectionProperty(DoctrineTestDto::class, 'roles');
        $result = $this->extractor->extract($reflection);

        $this->assertNotNull($result);
        $this->assertSame('array', $result['type']);
        $this->assertTrue($result['isCollection']);
    }

    public function testExtractFromDoctrineColumnString(): void
    {
        $reflection = new \ReflectionProperty(DoctrineTestDto::class, 'name');
        $result = $this->extractor->extract($reflection);

        $this->assertNotNull($result);
        $this->assertSame('string', $result['type']);
        $this->assertFalse($result['isCollection']);
    }

    public function testExtractFromDoctrineColumnInteger(): void
    {
        $reflection = new \ReflectionProperty(DoctrineTestDto::class, 'age');
        $result = $this->extractor->extract($reflection);

        $this->assertNotNull($result);
        $this->assertSame('integer', $result['type']);
    }

    public function testExtractFromDoctrineColumnJson(): void
    {
        $reflection = new \ReflectionProperty(DoctrineTestDto::class, 'metadata');
        $result = $this->extractor->extract($reflection);

        $this->assertNotNull($result);
        $this->assertSame('json', $result['type']);
    }

    public function testExtractFromDoctrineColumnNullable(): void
    {
        $reflection = new \ReflectionProperty(DoctrineTestDto::class, 'nickname');
        $result = $this->extractor->extract($reflection);

        $this->assertNotNull($result);
        $this->assertTrue($result['nullable']);
    }

    public function testConvertToSchemaString(): void
    {
        $doctrineInfo = ['type' => 'string', 'isCollection' => false, 'targetEntity' => null, 'nullable' => false, 'enum' => null];
        $schema = $this->extractor->convertToSchema($doctrineInfo);

        $this->assertSame(['type' => 'string'], $schema);
    }

    public function testConvertToSchemaInteger(): void
    {
        $doctrineInfo = ['type' => 'integer', 'isCollection' => false, 'targetEntity' => null, 'nullable' => false, 'enum' => null];
        $schema = $this->extractor->convertToSchema($doctrineInfo);

        $this->assertSame(['type' => 'integer', 'format' => 'int32'], $schema);
    }

    public function testConvertToSchemaSmallint(): void
    {
        $doctrineInfo = ['type' => 'smallint', 'isCollection' => false, 'targetEntity' => null, 'nullable' => false, 'enum' => null];
        $schema = $this->extractor->convertToSchema($doctrineInfo);

        $this->assertSame(['type' => 'integer', 'format' => 'int32'], $schema);
    }

    public function testConvertToSchemaFloat(): void
    {
        $doctrineInfo = ['type' => 'float', 'isCollection' => false, 'targetEntity' => null, 'nullable' => false, 'enum' => null];
        $schema = $this->extractor->convertToSchema($doctrineInfo);

        $this->assertSame(['type' => 'number', 'format' => 'float'], $schema);
    }

    public function testConvertToSchemaDecimal(): void
    {
        $doctrineInfo = ['type' => 'decimal', 'isCollection' => false, 'targetEntity' => null, 'nullable' => false, 'enum' => null];
        $schema = $this->extractor->convertToSchema($doctrineInfo);

        $this->assertSame(['type' => 'number', 'format' => 'float'], $schema);
    }

    public function testConvertToSchemaBoolean(): void
    {
        $doctrineInfo = ['type' => 'boolean', 'isCollection' => false, 'targetEntity' => null, 'nullable' => false, 'enum' => null];
        $schema = $this->extractor->convertToSchema($doctrineInfo);

        $this->assertSame(['type' => 'boolean'], $schema);
    }

    public function testConvertToSchemaDate(): void
    {
        $doctrineInfo = ['type' => 'date', 'isCollection' => false, 'targetEntity' => null, 'nullable' => false, 'enum' => null];
        $schema = $this->extractor->convertToSchema($doctrineInfo);

        $this->assertSame(['type' => 'string', 'format' => 'date-time'], $schema);
    }

    public function testConvertToSchemaDatetime(): void
    {
        $doctrineInfo = ['type' => 'datetime', 'isCollection' => false, 'targetEntity' => null, 'nullable' => false, 'enum' => null];
        $schema = $this->extractor->convertToSchema($doctrineInfo);

        $this->assertSame(['type' => 'string', 'format' => 'date-time'], $schema);
    }

    public function testConvertToSchemaDatetimeImmutable(): void
    {
        $doctrineInfo = ['type' => 'datetime_immutable', 'isCollection' => false, 'targetEntity' => null, 'nullable' => false, 'enum' => null];
        $schema = $this->extractor->convertToSchema($doctrineInfo);

        $this->assertSame(['type' => 'string', 'format' => 'date-time'], $schema);
    }

    public function testConvertToSchemaText(): void
    {
        $doctrineInfo = ['type' => 'text', 'isCollection' => false, 'targetEntity' => null, 'nullable' => false, 'enum' => null];
        $schema = $this->extractor->convertToSchema($doctrineInfo);

        $this->assertSame(['type' => 'string'], $schema);
    }

    public function testConvertToSchemaObject(): void
    {
        $doctrineInfo = ['type' => 'object', 'isCollection' => false, 'targetEntity' => null, 'nullable' => false, 'enum' => null];
        $schema = $this->extractor->convertToSchema($doctrineInfo);

        $this->assertSame(['type' => 'object'], $schema);
    }

    public function testConvertToSchemaArrayType(): void
    {
        $doctrineInfo = ['type' => 'array', 'isCollection' => true, 'targetEntity' => null, 'nullable' => false, 'enum' => null];
        $schema = $this->extractor->convertToSchema($doctrineInfo);

        $this->assertSame('array', $schema['type']);
        $this->assertArrayHasKey('items', $schema);
        $this->assertSame(['type' => 'array'], $schema['items']);
    }

    public function testConvertToSchemaJsonType(): void
    {
        $doctrineInfo = ['type' => 'json', 'isCollection' => true, 'targetEntity' => null, 'nullable' => false, 'enum' => null];
        $schema = $this->extractor->convertToSchema($doctrineInfo);

        $this->assertSame('array', $schema['type']);
    }

    public function testConvertToSchemaWithTargetEntity(): void
    {
        $doctrineInfo = ['type' => 'object', 'isCollection' => false, 'targetEntity' => 'AuthorDto', 'nullable' => false, 'enum' => null];
        $schema = $this->extractor->convertToSchema($doctrineInfo);

        $this->assertArrayHasKey('$ref', $schema);
        $this->assertStringContainsString('AuthorDto', $schema['$ref']);
    }

    public function testConvertToSchemaWithTargetEntityCollection(): void
    {
        $doctrineInfo = ['type' => 'array', 'isCollection' => true, 'targetEntity' => 'AuthorDto', 'nullable' => false, 'enum' => null];
        $schema = $this->extractor->convertToSchema($doctrineInfo);

        $this->assertSame('array', $schema['type']);
        $this->assertArrayHasKey('items', $schema);
        $this->assertArrayHasKey('$ref', $schema['items']);
    }

    public function testConvertToSchemaWithNamespace(): void
    {
        $doctrineInfo = ['type' => 'object', 'isCollection' => false, 'targetEntity' => 'AuthorDto', 'nullable' => false, 'enum' => null];
        $schema = $this->extractor->convertToSchema($doctrineInfo, 'SymfonySwagger\Tests\Analyzer');

        $this->assertArrayHasKey('$ref', $schema);
    }

    public function testConvertToSchemaWithEnum(): void
    {
        $doctrineInfo = ['type' => 'string', 'isCollection' => false, 'targetEntity' => null, 'nullable' => false, 'enum' => ['active', 'inactive']];
        $schema = $this->extractor->convertToSchema($doctrineInfo);

        $this->assertSame(['type' => 'string', 'enum' => ['active', 'inactive']], $schema);
    }

    public function testConvertToSchemaWithEnumCollection(): void
    {
        $doctrineInfo = ['type' => 'string', 'isCollection' => true, 'targetEntity' => null, 'nullable' => false, 'enum' => ['active', 'inactive']];
        $schema = $this->extractor->convertToSchema($doctrineInfo);

        $this->assertSame('array', $schema['type']);
        $this->assertArrayHasKey('items', $schema);
        $this->assertSame(['type' => 'string', 'enum' => ['active', 'inactive']], $schema['items']);
    }

    public function testConvertToSchemaDefaultType(): void
    {
        $doctrineInfo = ['type' => 'unknown', 'isCollection' => false, 'targetEntity' => null, 'nullable' => false, 'enum' => null];
        $schema = $this->extractor->convertToSchema($doctrineInfo);

        $this->assertSame(['type' => 'string'], $schema);
    }

    public function testExtractFromDoctrineManyToOne(): void
    {
        $reflection = new \ReflectionProperty(DoctrineRelationTestDto::class, 'author');
        $result = $this->extractor->extract($reflection);

        $this->assertNotNull($result);
        $this->assertSame('object', $result['type']);
        $this->assertFalse($result['isCollection']);
        $this->assertSame(AuthorDtoDoctrine::class, $result['targetEntity']);
        $this->assertTrue($result['nullable']);
    }

    public function testExtractFromDoctrineOneToMany(): void
    {
        $reflection = new \ReflectionProperty(DoctrineRelationTestDto::class, 'comments');
        $result = $this->extractor->extract($reflection);

        $this->assertNotNull($result);
        $this->assertSame('array', $result['type']);
        $this->assertTrue($result['isCollection']);
        $this->assertSame(CommentDto::class, $result['targetEntity']);
        $this->assertFalse($result['nullable']);
    }

    public function testExtractFromDoctrineManyToMany(): void
    {
        $reflection = new \ReflectionProperty(DoctrineRelationTestDto::class, 'tags');
        $result = $this->extractor->extract($reflection);

        $this->assertNotNull($result);
        $this->assertSame('array', $result['type']);
        $this->assertTrue($result['isCollection']);
    }

    public function testExtractFromDoctrineOneToOne(): void
    {
        $reflection = new \ReflectionProperty(DoctrineRelationTestDto::class, 'profile');
        $result = $this->extractor->extract($reflection);

        $this->assertNotNull($result);
        $this->assertSame('object', $result['type']);
        $this->assertFalse($result['isCollection']);
        $this->assertSame(ProfileDto::class, $result['targetEntity']);
        $this->assertTrue($result['nullable']);
    }

    public function testExtractFromSerializerTypeBuiltinCollection(): void
    {
        $reflection = new \ReflectionProperty(SerializerTypeTestDto::class, 'ids');
        $result = $this->extractor->extract($reflection);

        $this->assertNotNull($result);
        $this->assertSame('integer', $result['type']);
        $this->assertTrue($result['isCollection']);
        $this->assertNull($result['targetEntity']);
    }

    public function testExtractFromSerializerTypeTargetEntity(): void
    {
        $reflection = new \ReflectionProperty(SerializerTypeTestDto::class, 'authors');
        $result = $this->extractor->extract($reflection);

        $this->assertNotNull($result);
        $this->assertSame('object', $result['type']);
        $this->assertTrue($result['isCollection']);
        $this->assertSame('AuthorDtoDoctrine', $result['targetEntity']);
    }
}

// Test DTOs for Doctrine attributes

// Test DTOs for Doctrine attributes

class DocBlockTestDto
{
    /** @var string[] */
    public array $tags;
}

class DoctrineTestDto
{
    #[\Doctrine\ORM\Mapping\Column]
    public string $name;

    #[\Doctrine\ORM\Mapping\Column(type: 'integer')]
    public int $age;

    #[\Doctrine\ORM\Mapping\Column(nullable: true)]
    public ?string $nickname;

    #[\Doctrine\ORM\Mapping\Column(type: 'array')]
    public array $roles;

    #[\Doctrine\ORM\Mapping\Column(type: 'json')]
    public array $metadata;
}

class DoctrineRelationTestDto
{
    #[\Doctrine\ORM\Mapping\ManyToOne(targetEntity: AuthorDtoDoctrine::class)]
    public ?AuthorDtoDoctrine $author;

    #[\Doctrine\ORM\Mapping\OneToMany(targetEntity: CommentDto::class, mappedBy: 'author')]
    public array $comments;

    #[\Doctrine\ORM\Mapping\ManyToMany(targetEntity: ProfileDto::class)]
    public array $tags;

    #[\Doctrine\ORM\Mapping\OneToOne(targetEntity: ProfileDto::class)]
    public ?ProfileDto $profile;
}

class AuthorDtoDoctrine
{
    public string $name;
}

class CommentDto
{
    public string $text;
}

class ProfileDto
{
    public string $bio;
}

class UserDto
{
    public string $name;
}

class ItemDto
{
    public string $title;
}

class SerializerTypeTestDto
{
    #[Type('array<int, integer>')]
    public array $ids;

    #[Type('AuthorDtoDoctrine[]')]
    public array $authors;
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Type
{
    public function __construct(
        public readonly string $name,
    ) {
    }
}
