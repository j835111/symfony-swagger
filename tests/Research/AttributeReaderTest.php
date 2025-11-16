<?php

namespace SymfonySwagger\Tests\Research;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * 測試 Reflection API 讀取 Controller Attributes
 *
 * 此測試示範如何使用 PHP 8 Reflection API 擷取:
 * - 類別層級的 Attributes
 * - 方法層級的 Attributes
 * - 參數層級的 Attributes
 * - 型別資訊
 */
class AttributeReaderTest extends TestCase
{
    private ReflectionClass $reflectionClass;

    protected function setUp(): void
    {
        $this->reflectionClass = new ReflectionClass(ExampleController::class);
    }

    /**
     * 測試讀取類別層級的 Route Attribute
     */
    public function testReadClassLevelRouteAttribute(): void
    {
        $attributes = $this->reflectionClass->getAttributes(Route::class);

        $this->assertCount(1, $attributes, '應該有一個 Route attribute');

        $routeAttribute = $attributes[0]->newInstance();
        $this->assertInstanceOf(Route::class, $routeAttribute);
        $this->assertEquals('/api/posts', $routeAttribute->getPath());
    }

    /**
     * 測試讀取方法層級的 Route Attributes
     */
    public function testReadMethodLevelRouteAttributes(): void
    {
        $listMethod = $this->reflectionClass->getMethod('list');
        $attributes = $listMethod->getAttributes(Route::class);

        $this->assertCount(1, $attributes);

        $route = $attributes[0]->newInstance();
        $this->assertEquals('', $route->getPath());
        $this->assertEquals('posts_list', $route->getName());
        $this->assertEquals(['GET'], $route->getMethods());
    }

    /**
     * 測試讀取參數的 Attributes
     */
    public function testReadParameterAttributes(): void
    {
        $listMethod = $this->reflectionClass->getMethod('list');
        $parameters = $listMethod->getParameters();

        // 檢查第一個參數 $page
        $pageParam = $parameters[0];
        $this->assertEquals('page', $pageParam->getName());

        $attributes = $pageParam->getAttributes(MapQueryParameter::class);
        $this->assertCount(1, $attributes, '$page 參數應該有 MapQueryParameter attribute');

        // 檢查參數型別
        $type = $pageParam->getType();
        $this->assertNotNull($type);
        $this->assertEquals('int', $type->getName());
        $this->assertFalse($type->allowsNull());

        // 檢查預設值
        $this->assertTrue($pageParam->isOptional());
        $this->assertEquals(1, $pageParam->getDefaultValue());
    }

    /**
     * 測試讀取多個 Attributes
     */
    public function testReadMultipleAttributesOnMethod(): void
    {
        $createMethod = $this->reflectionClass->getMethod('create');

        // 檢查 Route attribute
        $routeAttrs = $createMethod->getAttributes(Route::class);
        $this->assertCount(1, $routeAttrs);

        // 檢查 IsGranted attribute
        $securityAttrs = $createMethod->getAttributes(IsGranted::class);
        $this->assertCount(1, $securityAttrs);

        $isGranted = $securityAttrs[0]->newInstance();
        $this->assertEquals('ROLE_USER', $isGranted->attribute);
    }

    /**
     * 測試讀取方法參數型別
     */
    public function testReadComplexParameterType(): void
    {
        $createMethod = $this->reflectionClass->getMethod('create');
        $parameters = $createMethod->getParameters();

        $postParam = $parameters[0];
        $this->assertEquals('post', $postParam->getName());

        // 檢查型別
        $type = $postParam->getType();
        $this->assertNotNull($type);
        $this->assertEquals(ExamplePostDto::class, $type->getName());

        // 檢查 Attribute
        $attributes = $postParam->getAttributes(MapRequestPayload::class);
        $this->assertCount(1, $attributes);
    }

    /**
     * 測試讀取方法回傳型別
     */
    public function testReadReturnType(): void
    {
        $listMethod = $this->reflectionClass->getMethod('list');
        $returnType = $listMethod->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('Symfony\Component\HttpFoundation\JsonResponse', $returnType->getName());
    }

    /**
     * 測試讀取所有 public 方法
     */
    public function testGetAllPublicMethods(): void
    {
        $methods = $this->reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

        // 過濾掉繼承的方法
        $declaredMethods = array_filter($methods, function (ReflectionMethod $method) {
            return $method->getDeclaringClass()->getName() === ExampleController::class;
        });

        $methodNames = array_map(fn($m) => $m->getName(), $declaredMethods);

        $this->assertContains('list', $methodNames);
        $this->assertContains('show', $methodNames);
        $this->assertContains('create', $methodNames);
        $this->assertContains('update', $methodNames);
        $this->assertContains('delete', $methodNames);
        $this->assertContains('search', $methodNames);
    }

    /**
     * 測試讀取 PHPDoc 註解
     */
    public function testReadDocComment(): void
    {
        $listMethod = $this->reflectionClass->getMethod('list');
        $docComment = $listMethod->getDocComment();

        $this->assertNotFalse($docComment);
        $this->assertStringContainsString('取得文章列表', $docComment);
        $this->assertStringContainsString('支援分頁和篩選', $docComment);
    }

    /**
     * 測試讀取可選參數
     */
    public function testReadOptionalParameter(): void
    {
        $listMethod = $this->reflectionClass->getMethod('list');
        $parameters = $listMethod->getParameters();

        // $search 參數
        $searchParam = $parameters[2];
        $this->assertEquals('search', $searchParam->getName());

        // 檢查可選
        $this->assertTrue($searchParam->isOptional());
        $this->assertNull($searchParam->getDefaultValue());

        // 檢查 nullable
        $type = $searchParam->getType();
        $this->assertTrue($type->allowsNull());
        $this->assertEquals('string', $type->getName());
    }

    /**
     * 測試讀取 Requirements
     */
    public function testReadRouteRequirements(): void
    {
        $showMethod = $this->reflectionClass->getMethod('show');
        $attributes = $showMethod->getAttributes(Route::class);

        $route = $attributes[0]->newInstance();
        $requirements = $route->getRequirements();

        $this->assertArrayHasKey('id', $requirements);
        $this->assertEquals('\d+', $requirements['id']);
    }

    /**
     * 綜合測試:完整分析一個方法
     */
    public function testCompleteMethodAnalysis(): void
    {
        $updateMethod = $this->reflectionClass->getMethod('update');

        // 1. 方法基本資訊
        $this->assertTrue($updateMethod->isPublic());
        $this->assertEquals('update', $updateMethod->getName());

        // 2. Route Attribute
        $routeAttrs = $updateMethod->getAttributes(Route::class);
        $route = $routeAttrs[0]->newInstance();
        $this->assertEquals('/{id}', $route->getPath());
        $this->assertEquals(['PUT'], $route->getMethods());
        $this->assertEquals('posts_update', $route->getName());

        // 3. Security Attribute
        $securityAttrs = $updateMethod->getAttributes(IsGranted::class);
        $isGranted = $securityAttrs[0]->newInstance();
        $this->assertEquals('ROLE_EDITOR', $isGranted->attribute);

        // 4. 參數分析
        $parameters = $updateMethod->getParameters();
        $this->assertCount(2, $parameters);

        // 第一個參數: $id
        $idParam = $parameters[0];
        $this->assertEquals('id', $idParam->getName());
        $this->assertEquals('int', $idParam->getType()->getName());
        $this->assertFalse($idParam->isOptional());

        // 第二個參數: $post
        $postParam = $parameters[1];
        $this->assertEquals('post', $postParam->getName());
        $this->assertEquals(ExamplePostDto::class, $postParam->getType()->getName());
        $this->assertCount(1, $postParam->getAttributes(MapRequestPayload::class));

        // 5. 回傳型別
        $returnType = $updateMethod->getReturnType();
        $this->assertEquals('Symfony\Component\HttpFoundation\JsonResponse', $returnType->getName());
    }

    /**
     * 測試分析 DTO 類別
     */
    public function testAnalyzeDtoClass(): void
    {
        $dtoReflection = new ReflectionClass(ExamplePostDto::class);
        $properties = $dtoReflection->getProperties();

        $this->assertGreaterThan(0, count($properties));

        // 分析 title 屬性
        $titleProp = $dtoReflection->getProperty('title');
        $this->assertEquals('title', $titleProp->getName());
        $this->assertEquals('string', $titleProp->getType()->getName());

        // 讀取驗證 Attributes
        $attributes = $titleProp->getAttributes();
        $this->assertGreaterThan(0, count($attributes));

        // 檢查是否有 Assert\NotBlank
        $notBlankAttrs = $titleProp->getAttributes(\Symfony\Component\Validator\Constraints\NotBlank::class);
        $this->assertCount(1, $notBlankAttrs);
    }
}
