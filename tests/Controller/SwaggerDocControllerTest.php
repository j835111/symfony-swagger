<?php

declare(strict_types=1);

namespace SymfonySwagger\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use SymfonySwagger\Controller\SwaggerDocController;
use SymfonySwagger\Service\OpenApiGenerator;

/**
 * Test case for SwaggerDocController.
 */
class SwaggerDocControllerTest extends TestCase
{
    private SwaggerDocController $controller;
    private OpenApiGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = $this->createMock(OpenApiGenerator::class);
        $this->controller = new SwaggerDocController($this->generator);
    }

    public function testDocumentationReturnsJsonResponse(): void
    {
        $expectedDoc = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'Test API',
                'version' => '1.0.0',
            ],
            'paths' => [],
        ];
        
        $this->generator->expects($this->once())
            ->method('generate')
            ->willReturn($expectedDoc);
        
        $response = $this->controller->documentation();
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(JsonResponse::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('application/json', $response->headers->get('Content-Type'));
    }

    public function testDocumentationReturnsCorrectContent(): void
    {
        $expectedDoc = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'My API',
                'version' => '2.0.0',
            ],
            'paths' => [
                '/users' => [
                    'get' => [
                        'summary' => 'List users',
                    ],
                ],
            ],
        ];
        
        $this->generator->expects($this->once())
            ->method('generate')
            ->willReturn($expectedDoc);
        
        $response = $this->controller->documentation();
        
        $content = json_decode($response->getContent(), true);
        
        $this->assertEquals('3.1.0', $content['openapi']);
        $this->assertEquals('My API', $content['info']['title']);
        $this->assertEquals('2.0.0', $content['info']['version']);
        $this->assertArrayHasKey('/users', $content['paths']);
    }

    public function testDocumentationRouteHasCorrectName(): void
    {
        // Verify the route attribute is properly defined
        $reflectionMethod = new \ReflectionMethod($this->controller, 'documentation');
        $attributes = $reflectionMethod->getAttributes(\Symfony\Component\Routing\Attribute\Route::class);
        
        $this->assertNotEmpty($attributes);
        
        $routeAttribute = $attributes[0]->newInstance();
        
        $this->assertEquals('/api/docs.json', $routeAttribute->getPath());
        $this->assertContains('GET', $routeAttribute->getMethods());
        $this->assertEquals('symfony_swagger_doc', $routeAttribute->getName());
    }

    public function testSwaggerUiReturnsHtmlResponse(): void
    {
        $response = $this->controller->swaggerUi();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('swagger-ui', (string) $response->getContent());
    }

    public function testSwaggerUiRouteHasCorrectName(): void
    {
        $reflectionMethod = new \ReflectionMethod($this->controller, 'swaggerUi');
        $attributes = $reflectionMethod->getAttributes(\Symfony\Component\Routing\Attribute\Route::class);

        $this->assertNotEmpty($attributes);

        $routeAttribute = $attributes[0]->newInstance();

        $this->assertEquals('/api/docs', $routeAttribute->getPath());
        $this->assertContains('GET', $routeAttribute->getMethods());
        $this->assertEquals('symfony_swagger_ui', $routeAttribute->getName());
    }

    public function testScalarReturnsHtmlResponse(): void
    {
        $response = $this->controller->scalar();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('scalar-api-reference', (string) $response->getContent());
    }

    public function testScalarRouteHasCorrectName(): void
    {
        $reflectionMethod = new \ReflectionMethod($this->controller, 'scalar');
        $attributes = $reflectionMethod->getAttributes(\Symfony\Component\Routing\Attribute\Route::class);

        $this->assertNotEmpty($attributes);

        $routeAttribute = $attributes[0]->newInstance();

        $this->assertEquals('/api/docs/scalar', $routeAttribute->getPath());
        $this->assertContains('GET', $routeAttribute->getMethods());
        $this->assertEquals('symfony_swagger_scalar', $routeAttribute->getName());
    }
}
