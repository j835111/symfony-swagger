<?php

declare(strict_types=1);

namespace SymfonySwagger\Tests\Analyzer;

use PHPUnit\Framework\TestCase;
use SymfonySwagger\Analyzer\DocBlockDescriptionExtractor;

class DocBlockDescriptionExtractorTest extends TestCase
{
    public function testGetSchemaDescriptionReturnsNullWithoutDocBlock(): void
    {
        $reflection = new \ReflectionProperty(DocBlockDescriptionFixture::class, 'plain');

        $this->assertNull(DocBlockDescriptionExtractor::getSchemaDescription($reflection));
    }

    public function testGetSummaryAndOperationDescriptionFromBody(): void
    {
        $reflection = new \ReflectionMethod(DocBlockDescriptionFixture::class, 'fromBody');

        $this->assertSame('Short summary.', DocBlockDescriptionExtractor::getSummary($reflection));
        $this->assertSame('Detailed explanation.', DocBlockDescriptionExtractor::getOperationDescription($reflection));
    }

    public function testGetOperationDescriptionFromExplicitDescriptionTag(): void
    {
        $reflection = new \ReflectionMethod(DocBlockDescriptionFixture::class, 'fromExplicitDescription');

        $this->assertSame('Explicit summary', DocBlockDescriptionExtractor::getSummary($reflection));
        $this->assertSame("First line\nSecond line", DocBlockDescriptionExtractor::getOperationDescription($reflection));
    }

    public function testGetParameterDescriptionSupportsContinuationLines(): void
    {
        $reflection = new \ReflectionMethod(DocBlockDescriptionFixture::class, 'withParams');

        $this->assertSame("Main identifier\ncontinues here", DocBlockDescriptionExtractor::getParameterDescription($reflection, 'id'));
        $this->assertSame('Filter keyword', DocBlockDescriptionExtractor::getParameterDescription($reflection, 'keyword'));
        $this->assertNull(DocBlockDescriptionExtractor::getParameterDescription($reflection, 'missing'));
    }

    public function testGetSchemaDescriptionReturnsFullBodyForClassDocBlock(): void
    {
        $reflection = new \ReflectionClass(DocumentedDtoFixture::class);

        $this->assertSame("DTO summary.\n\nDTO details.", DocBlockDescriptionExtractor::getSchemaDescription($reflection));
    }

    public function testGetOperationDescriptionReturnsNullWhenOnlySummaryExists(): void
    {
        $reflection = new \ReflectionMethod(DocBlockDescriptionFixture::class, 'onlySummary');

        $this->assertSame('Summary only.', DocBlockDescriptionExtractor::getSummary($reflection));
        $this->assertNull(DocBlockDescriptionExtractor::getOperationDescription($reflection));
    }

    public function testGetDescriptionsReturnNullForTagOnlyDocBlock(): void
    {
        $reflection = new \ReflectionMethod(DocBlockDescriptionFixture::class, 'tagsOnly');

        $this->assertNull(DocBlockDescriptionExtractor::getSummary($reflection));
        $this->assertNull(DocBlockDescriptionExtractor::getOperationDescription($reflection));
        $this->assertNull(DocBlockDescriptionExtractor::getSchemaDescription($reflection));
    }

    public function testGetParameterDescriptionUsesContinuationWhenInlineDescriptionIsMissing(): void
    {
        $reflection = new \ReflectionMethod(DocBlockDescriptionFixture::class, 'withContinuationOnly');

        $this->assertSame('continued detail', DocBlockDescriptionExtractor::getParameterDescription($reflection, 'id'));
    }

    public function testGetSchemaDescriptionFallsBackToSummaryWhenOnlySummaryExists(): void
    {
        $reflection = new \ReflectionClass(SummaryOnlyDtoFixture::class);

        $this->assertSame('DTO summary only.', DocBlockDescriptionExtractor::getSchemaDescription($reflection));
    }
}

class DocBlockDescriptionFixture
{
    public string $plain;

    /**
     * Short summary.
     *
     * Detailed explanation.
     */
    public function fromBody(): void
    {
    }

    /**
     * Summary only.
     */
    public function onlySummary(): void
    {
    }

    /**
     * @summary Explicit summary
     *
     * @description First line
     * Second line
     *
     * @deprecated
     */
    public function fromExplicitDescription(): void
    {
    }

    /**
     * @param int $id Main identifier
     *                continues here
     * @param string $keyword Filter keyword
     */
    public function withParams(int $id, string $keyword): void
    {
    }

    /**
     * @param int $id
     *                continued detail
     */
    public function withContinuationOnly(int $id): void
    {
    }

    /**
     * @deprecated
     */
    public function tagsOnly(): void
    {
    }
}

/**
 * DTO summary.
 *
 * DTO details.
 */
class DocumentedDtoFixture
{
}

/**
 * DTO summary only.
 */
class SummaryOnlyDtoFixture
{
}
