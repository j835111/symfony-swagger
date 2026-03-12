<?php

declare(strict_types=1);

namespace SymfonySwagger\Tests\Service;

use PHPUnit\Framework\TestCase;
use SymfonySwagger\Service\OpenApiGenerator;
use SymfonySwagger\Service\SwaggerGenerator;

class SwaggerGeneratorTest extends TestCase
{
    public function testGenerateDelegatesToOpenApiGenerator(): void
    {
        $expected = ['openapi' => '3.1.0', 'paths' => []];
        $openApi = $this->createMock(OpenApiGenerator::class);
        $openApi->expects($this->once())
            ->method('generate')
            ->willReturn($expected);

        $generator = new SwaggerGenerator([], $openApi);

        $this->assertSame($expected, $generator->generate());
    }

    public function testSetGeneratorOverridesNullGenerator(): void
    {
        $expected = ['openapi' => '3.1.0', 'paths' => ['/' => []]];
        $openApi = $this->createMock(OpenApiGenerator::class);
        $openApi->expects($this->once())
            ->method('generate')
            ->willReturn($expected);

        $generator = new SwaggerGenerator([]);
        $generator->setGenerator($openApi);

        $this->assertSame($expected, $generator->generate());
    }

    public function testGenerateFallsBackToConfig(): void
    {
        $config = [
            'info' => [
                'title' => 'My API',
                'description' => 'Docs',
                'version' => '2.0.0',
            ],
            'servers' => [
                ['url' => 'https://example.test', 'description' => 'main'],
            ],
        ];

        $generator = new SwaggerGenerator($config);
        $result = $generator->generate();

        $this->assertSame('3.1.0', $result['openapi']);
        $this->assertSame('My API', $result['info']['title']);
        $this->assertSame('Docs', $result['info']['description']);
        $this->assertSame('2.0.0', $result['info']['version']);
        $this->assertSame($config['servers'], $result['servers']);
        $this->assertSame([], $result['paths']);
    }

    public function testGetConfigReturnsOriginalConfig(): void
    {
        $config = ['info' => ['title' => 'Test']];
        $generator = new SwaggerGenerator($config);

        $this->assertSame($config, $generator->getConfig());
    }
}
