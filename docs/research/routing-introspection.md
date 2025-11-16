# Symfony 7.x Routing 資訊擷取方法研究

## 概述

本文檔研究在 Symfony 7.x 中擷取 API routing 資訊的三種主要方法:
1. **Compiler Pass** (編譯期擷取)
2. **Runtime Service** (執行期擷取)
3. **Console Command** (命令列擷取)

並分析各方法的優缺點、適用場景及實作細節。

---

## 方法一:Compiler Pass (編譯期擷取)

### 原理

Compiler Pass 在 Symfony 容器編譯階段執行,可以在此階段修改服務定義、收集資訊並建立快取。

### 適用場景

- **生產環境部署時**一次性生成 OpenAPI 文檔
- 需要**高效能**的場景(避免執行期開銷)
- **靜態路由**為主的應用程式

### 優點

✅ **效能最佳**:僅在容器編譯時執行一次
✅ **完整的路由資訊**:可存取整個 `RouteCollection`
✅ **可快取結果**:生成的資料可儲存為檔案或服務參數
✅ **不影響執行期效能**:零執行期開銷

### 缺點

❌ **開發時不便**:需要清除快取(`cache:clear`)才能看到變更
❌ **無法處理動態路由**:若路由在執行期動態生成則無法捕捉
❌ **調試困難**:Compiler Pass 的執行時機特殊,難以調試

### 實作範例

```php
<?php

namespace SymfonySwagger\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use ReflectionClass;
use ReflectionMethod;

/**
 * OpenAPI 文檔生成 Compiler Pass
 *
 * 在容器編譯階段擷取所有路由資訊並分析 Controller Attributes
 */
class OpenApiGeneratorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // 檢查 router 服務是否存在
        if (!$container->hasDefinition('router.default')) {
            return;
        }

        // 取得 Router 服務定義
        $routerDefinition = $container->findDefinition('router.default');
        $routeCollectionBuilder = $routerDefinition->getArgument(1);

        // 從 routing files 載入路由
        // 注意:此階段無法直接取得完整的 RouteCollection
        // 需要另外的策略

        $apiRoutes = $this->extractApiRoutes($container);

        // 將擷取的資訊儲存為服務參數
        $container->setParameter('symfony_swagger.api_routes', $apiRoutes);
    }

    /**
     * 擷取 API 路由資訊
     */
    private function extractApiRoutes(ContainerBuilder $container): array
    {
        $routes = [];

        // 取得所有標記為 controller 的服務
        $controllers = $container->findTaggedServiceIds('controller.service_arguments');

        foreach ($controllers as $serviceId => $tags) {
            $definition = $container->findDefinition($serviceId);
            $class = $definition->getClass();

            if (!class_exists($class)) {
                continue;
            }

            try {
                $reflectionClass = new ReflectionClass($class);
                $routes = array_merge($routes, $this->analyzeController($reflectionClass));
            } catch (\ReflectionException $e) {
                // Skip invalid classes
                continue;
            }
        }

        return $routes;
    }

    /**
     * 分析 Controller 類別並擷取路由資訊
     */
    private function analyzeController(ReflectionClass $reflectionClass): array
    {
        $routes = [];

        // 讀取類別層級的 Route Attribute
        $classRoute = null;
        foreach ($reflectionClass->getAttributes() as $attribute) {
            if ($attribute->getName() === Route::class) {
                $classRoute = $attribute->newInstance();
                break;
            }
        }

        // 分析每個 public 方法
        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // 跳過繼承的方法
            if ($method->getDeclaringClass()->getName() !== $reflectionClass->getName()) {
                continue;
            }

            $routeData = $this->analyzeMethod($method, $classRoute);
            if ($routeData) {
                $routes[] = $routeData;
            }
        }

        return $routes;
    }

    /**
     * 分析方法並擷取路由 Attribute
     */
    private function analyzeMethod(ReflectionMethod $method, ?Route $classRoute): ?array
    {
        $methodRoute = null;

        // 尋找 Route Attribute
        foreach ($method->getAttributes() as $attribute) {
            if ($attribute->getName() === Route::class) {
                $methodRoute = $attribute->newInstance();
                break;
            }
        }

        if (!$methodRoute) {
            return null;
        }

        // 組合類別與方法層級的路徑
        $path = ($classRoute ? $classRoute->getPath() : '') . $methodRoute->getPath();

        return [
            'path' => $path,
            'methods' => $methodRoute->getMethods() ?: ['GET'],
            'name' => $methodRoute->getName(),
            'controller' => $method->getDeclaringClass()->getName() . '::' . $method->getName(),
            'requirements' => $methodRoute->getRequirements(),
            'defaults' => $methodRoute->getDefaults(),
            'parameters' => $this->analyzeParameters($method),
            'returnType' => $method->getReturnType()?->getName(),
        ];
    }

    /**
     * 分析方法參數
     */
    private function analyzeParameters(ReflectionMethod $method): array
    {
        $parameters = [];

        foreach ($method->getParameters() as $param) {
            $parameters[] = [
                'name' => $param->getName(),
                'type' => $param->getType()?->getName(),
                'isOptional' => $param->isOptional(),
                'defaultValue' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
                'attributes' => $this->getParameterAttributes($param),
            ];
        }

        return $parameters;
    }

    /**
     * 取得參數的 Attributes
     */
    private function getParameterAttributes(\ReflectionParameter $param): array
    {
        $attributes = [];

        foreach ($param->getAttributes() as $attribute) {
            $attributes[] = [
                'class' => $attribute->getName(),
                'arguments' => $attribute->getArguments(),
            ];
        }

        return $attributes;
    }
}
```

**註冊 Compiler Pass**:

```php
<?php

namespace SymfonySwagger;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use SymfonySwagger\DependencyInjection\Compiler\OpenApiGeneratorPass;

class SymfonySwaggerBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new OpenApiGeneratorPass());
    }
}
```

### 限制與解決方案

**限制 1: 無法直接存取 RouteCollection**

在 Compiler Pass 階段,路由尚未完全載入到 RouteCollection,因此無法直接透過 `$router->getRouteCollection()` 取得。

**解決方案**:
- 使用 **tagged service** 方式分析所有標記為 `controller.service_arguments` 的服務
- 透過 **Reflection API** 直接讀取 Controller 類別的 Attributes
- 或使用 **Custom Route Loader** 在路由載入階段收集資訊

**限制 2: 開發時需頻繁清除快取**

**解決方案**:
- 在開發環境使用 Runtime 方式
- 在生產環境使用 Compiler Pass
- 提供環境切換機制

---

## 方法二:Runtime Service (執行期擷取)

### 原理

在執行期透過注入 `RouterInterface` 服務,動態取得 `RouteCollection` 並分析路由資訊。

### 適用場景

- **開發環境**:即時看到路由變更,無需清除快取
- **動態路由**:支援執行期動態註冊的路由
- **Web UI**:在 Swagger UI 頁面載入時即時生成文檔

### 優點

✅ **即時更新**:開發時修改 Controller 立即生效
✅ **支援動態路由**:可捕捉執行期註冊的路由
✅ **易於調試**:可在 Controller/Service 中直接調試
✅ **實作簡單**:使用 Symfony 標準服務注入

### 缺點

❌ **效能開銷**:每次請求都需要分析(需要快取)
❌ **記憶體使用**:Reflection 操作消耗記憶體
❌ **響應時間**:首次載入較慢

### 實作範例

```php
<?php

namespace SymfonySwagger\Service;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Routing\Route;
use ReflectionClass;
use ReflectionMethod;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Runtime OpenAPI 文檔生成器
 */
class OpenApiGenerator
{
    private RouterInterface $router;
    private CacheItemPoolInterface $cache;
    private int $cacheTtl;

    public function __construct(
        RouterInterface $router,
        CacheItemPoolInterface $cache,
        int $cacheTtl = 3600
    ) {
        $this->router = $router;
        $this->cache = $cache;
        $this->cacheTtl = $cacheTtl;
    }

    /**
     * 生成 OpenAPI 文檔
     */
    public function generate(): array
    {
        // 檢查快取
        $cacheKey = 'openapi_doc_' . md5($_ENV['APP_ENV'] ?? 'dev');
        $cacheItem = $this->cache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        // 生成文檔
        $routes = $this->extractRoutes();
        $openApiDoc = $this->buildOpenApiDocument($routes);

        // 儲存快取
        $cacheItem->set($openApiDoc);
        $cacheItem->expiresAfter($this->cacheTtl);
        $this->cache->save($cacheItem);

        return $openApiDoc;
    }

    /**
     * 擷取所有路由資訊
     */
    private function extractRoutes(): array
    {
        $routes = [];
        $routeCollection = $this->router->getRouteCollection();

        foreach ($routeCollection->all() as $name => $route) {
            // 跳過內部路由(以 _ 開頭)
            if (str_starts_with($name, '_')) {
                continue;
            }

            $controller = $route->getDefault('_controller');
            if (!$controller) {
                continue;
            }

            // 解析 Controller::method 格式
            $routeData = $this->analyzeRoute($name, $route, $controller);
            if ($routeData) {
                $routes[] = $routeData;
            }
        }

        return $routes;
    }

    /**
     * 分析單一路由
     */
    private function analyzeRoute(string $name, Route $route, string $controller): ?array
    {
        // 解析 controller 字串
        if (!str_contains($controller, '::')) {
            return null;  // __invoke or other format
        }

        [$class, $method] = explode('::', $controller, 2);

        if (!class_exists($class)) {
            return null;
        }

        try {
            $reflectionClass = new ReflectionClass($class);
            $reflectionMethod = $reflectionClass->getMethod($method);
        } catch (\ReflectionException $e) {
            return null;
        }

        return [
            'name' => $name,
            'path' => $route->getPath(),
            'methods' => $route->getMethods() ?: ['GET'],
            'controller' => $controller,
            'requirements' => $route->getRequirements(),
            'defaults' => $route->getDefaults(),
            'condition' => $route->getCondition(),
            'attributes' => $this->extractAttributes($reflectionMethod),
            'parameters' => $this->extractParameters($reflectionMethod),
            'returnType' => $reflectionMethod->getReturnType()?->getName(),
        ];
    }

    /**
     * 擷取方法的 Attributes
     */
    private function extractAttributes(ReflectionMethod $method): array
    {
        $attributes = [];

        foreach ($method->getAttributes() as $attribute) {
            $instance = $attribute->newInstance();
            $attributes[] = [
                'class' => $attribute->getName(),
                'instance' => $instance,
            ];
        }

        return $attributes;
    }

    /**
     * 擷取方法參數資訊
     */
    private function extractParameters(ReflectionMethod $method): array
    {
        $parameters = [];

        foreach ($method->getParameters() as $param) {
            $type = $param->getType();

            $parameters[] = [
                'name' => $param->getName(),
                'type' => $type ? $type->getName() : 'mixed',
                'isOptional' => $param->isOptional(),
                'defaultValue' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
                'isNullable' => $type && $type->allowsNull(),
                'attributes' => $this->getParameterAttributes($param),
            ];
        }

        return $parameters;
    }

    /**
     * 取得參數 Attributes
     */
    private function getParameterAttributes(\ReflectionParameter $param): array
    {
        $attributes = [];

        foreach ($param->getAttributes() as $attribute) {
            $attributes[] = [
                'class' => $attribute->getName(),
                'instance' => $attribute->newInstance(),
            ];
        }

        return $attributes;
    }

    /**
     * 建構 OpenAPI 文檔
     */
    private function buildOpenApiDocument(array $routes): array
    {
        // TODO: 將路由資料轉換為 OpenAPI 規範
        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'API Documentation',
                'version' => '1.0.0',
            ],
            'paths' => $this->buildPaths($routes),
        ];
    }

    private function buildPaths(array $routes): array
    {
        $paths = [];

        foreach ($routes as $route) {
            $path = $route['path'];
            $methods = $route['methods'];

            foreach ($methods as $method) {
                $paths[$path][strtolower($method)] = [
                    'operationId' => $route['name'],
                    'summary' => '', // TODO: 從 Attribute 擷取
                    'parameters' => [], // TODO: 轉換參數
                    'responses' => [], // TODO: 轉換回應
                ];
            }
        }

        return $paths;
    }
}
```

**服務定義** (`config/services.php`):

```php
<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use SymfonySwagger\Service\OpenApiGenerator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure();

    $services->set(OpenApiGenerator::class)
        ->arg('$cacheTtl', 3600)  // 快取 1 小時
    ;
};
```

**Controller 使用**:

```php
<?php

namespace SymfonySwagger\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use SymfonySwagger\Service\OpenApiGenerator;

class SwaggerController extends AbstractController
{
    #[Route('/api/doc.json', name: 'swagger_json', methods: ['GET'])]
    public function json(OpenApiGenerator $generator): JsonResponse
    {
        return $this->json($generator->generate());
    }
}
```

### 效能優化策略

#### 1. 多層快取

```php
// L1: Request 快取(單次請求內)
private ?array $cachedDoc = null;

public function generate(): array
{
    if ($this->cachedDoc) {
        return $this->cachedDoc;
    }

    // L2: Symfony Cache (跨請求)
    $cacheItem = $this->cache->getItem('openapi_doc');
    if ($cacheItem->isHit()) {
        return $this->cachedDoc = $cacheItem->get();
    }

    // 生成文檔
    $doc = $this->extractAndBuild();

    $cacheItem->set($doc);
    $cacheItem->expiresAfter(3600);
    $this->cache->save($cacheItem);

    return $this->cachedDoc = $doc;
}
```

#### 2. 環境區分

```php
public function __construct(
    RouterInterface $router,
    CacheItemPoolInterface $cache,
    string $environment
) {
    $this->cacheTtl = $environment === 'prod' ? 86400 : 60;  // 生產環境快取 24 小時
}
```

#### 3. Lazy Loading

僅在首次存取 `/api/doc.json` 時才生成文檔,避免每次請求都執行。

---

## 方法三:Console Command (命令列擷取)

### 原理

透過 Console Command 手動執行文檔生成,將結果儲存為靜態檔案 (JSON/YAML)。

### 適用場景

- **CI/CD 整合**:在部署時自動生成文檔
- **靜態文檔**:生成後提交到版本控制或 CDN
- **不希望執行期開銷**:完全避免 Runtime 分析

### 優點

✅ **可控性高**:開發者決定何時生成
✅ **無執行期開銷**:文檔是靜態檔案
✅ **易於整合 CI/CD**:可在部署流程中自動執行
✅ **可人工審查**:生成的檔案可以 code review

### 缺點

❌ **需手動執行**:不會自動更新
❌ **可能與程式碼不同步**:若忘記執行會產生過時文檔
❌ **需要儲存空間**:生成的檔案需要版本控制

### 實作範例

```php
<?php

namespace SymfonySwagger\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Routing\Route;
use ReflectionClass;
use ReflectionMethod;

/**
 * 生成 OpenAPI 文檔的 Console Command
 */
#[AsCommand(
    name: 'swagger:generate',
    description: '生成 OpenAPI 文檔'
)]
class GenerateSwaggerCommand extends Command
{
    private RouterInterface $router;
    private string $projectDir;

    public function __construct(
        RouterInterface $router,
        string $projectDir
    ) {
        parent::__construct();
        $this->router = $router;
        $this->projectDir = $projectDir;
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                '輸出檔案路徑',
                'public/api-doc.json'
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_REQUIRED,
                '輸出格式 (json|yaml)',
                'json'
            )
            ->addOption(
                'filter',
                null,
                InputOption::VALUE_REQUIRED,
                '路由前綴過濾 (例如: /api)',
                null
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('OpenAPI 文檔生成器');

        // 擷取選項
        $outputFile = $input->getOption('output');
        $format = $input->getOption('format');
        $filter = $input->getOption('filter');

        // 驗證格式
        if (!in_array($format, ['json', 'yaml'])) {
            $io->error('不支援的格式,僅支援 json 或 yaml');
            return Command::FAILURE;
        }

        // 擷取路由
        $io->section('擷取路由資訊...');
        $routes = $this->extractRoutes($filter);
        $io->success(sprintf('找到 %d 個路由', count($routes)));

        // 生成文檔
        $io->section('生成 OpenAPI 文檔...');
        $document = $this->buildOpenApiDocument($routes);

        // 輸出檔案
        $outputPath = $this->projectDir . '/' . $outputFile;
        $this->saveDocument($document, $outputPath, $format);

        $io->success('文檔已生成: ' . $outputPath);

        // 顯示統計資訊
        $io->table(
            ['指標', '數量'],
            [
                ['路由總數', count($routes)],
                ['API 端點', count($document['paths'] ?? [])],
                ['檔案大小', $this->formatBytes(filesize($outputPath))],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * 擷取路由資訊
     */
    private function extractRoutes(?string $filter): array
    {
        $routes = [];
        $routeCollection = $this->router->getRouteCollection();

        foreach ($routeCollection->all() as $name => $route) {
            // 跳過內部路由
            if (str_starts_with($name, '_')) {
                continue;
            }

            // 路徑過濾
            if ($filter && !str_starts_with($route->getPath(), $filter)) {
                continue;
            }

            $controller = $route->getDefault('_controller');
            if (!$controller || !str_contains($controller, '::')) {
                continue;
            }

            [$class, $method] = explode('::', $controller, 2);

            if (!class_exists($class)) {
                continue;
            }

            try {
                $reflectionClass = new ReflectionClass($class);
                $reflectionMethod = $reflectionClass->getMethod($method);

                $routes[] = [
                    'name' => $name,
                    'path' => $route->getPath(),
                    'methods' => $route->getMethods() ?: ['GET'],
                    'controller' => $controller,
                    'reflection' => $reflectionMethod,
                    'requirements' => $route->getRequirements(),
                    'defaults' => $route->getDefaults(),
                ];
            } catch (\ReflectionException $e) {
                continue;
            }
        }

        return $routes;
    }

    /**
     * 建構 OpenAPI 文檔
     */
    private function buildOpenApiDocument(array $routes): array
    {
        $paths = [];

        foreach ($routes as $route) {
            $path = $this->convertPathToOpenApi($route['path']);

            foreach ($route['methods'] as $method) {
                $paths[$path][strtolower($method)] = [
                    'operationId' => $route['name'],
                    'summary' => $this->extractSummary($route['reflection']),
                    'tags' => $this->extractTags($route['controller']),
                    'parameters' => $this->buildParameters($route),
                    'responses' => $this->buildResponses($route['reflection']),
                ];
            }
        }

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'API Documentation',
                'version' => '1.0.0',
                'description' => '自動生成的 API 文檔',
            ],
            'paths' => $paths,
        ];
    }

    /**
     * 轉換 Symfony 路徑格式為 OpenAPI 格式
     *
     * Symfony: /blog/{slug}
     * OpenAPI: /blog/{slug}
     */
    private function convertPathToOpenApi(string $path): string
    {
        // Symfony 與 OpenAPI 的參數格式相同
        return $path;
    }

    /**
     * 從 PHPDoc 或 Attribute 擷取摘要
     */
    private function extractSummary(ReflectionMethod $method): string
    {
        $docComment = $method->getDocComment();
        if ($docComment) {
            // 簡單解析 PHPDoc 第一行
            preg_match('/@summary\s+(.+)/', $docComment, $matches);
            if ($matches) {
                return trim($matches[1]);
            }
        }

        // 預設使用方法名稱
        return ucfirst(str_replace('_', ' ', $method->getName()));
    }

    /**
     * 從 Controller 類別名稱擷取 tags
     */
    private function extractTags(string $controller): array
    {
        [$class, $method] = explode('::', $controller);
        $shortClass = (new \ReflectionClass($class))->getShortName();

        // 移除 "Controller" 後綴
        $tag = str_replace('Controller', '', $shortClass);

        return [$tag];
    }

    /**
     * 建構參數定義
     */
    private function buildParameters(array $route): array
    {
        $parameters = [];

        // 路徑參數
        preg_match_all('/{(\w+)}/', $route['path'], $matches);
        foreach ($matches[1] as $param) {
            $parameters[] = [
                'name' => $param,
                'in' => 'path',
                'required' => true,
                'schema' => [
                    'type' => 'string',  // TODO: 從 requirements 推導型別
                ],
            ];
        }

        // TODO: 從方法參數的 Attributes 擷取 query parameters

        return $parameters;
    }

    /**
     * 建構回應定義
     */
    private function buildResponses(ReflectionMethod $method): array
    {
        $returnType = $method->getReturnType()?->getName();

        return [
            '200' => [
                'description' => 'Successful response',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',  // TODO: 分析回傳型別
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * 儲存文檔
     */
    private function saveDocument(array $document, string $path, string $format): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $content = match($format) {
            'json' => json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'yaml' => yaml_emit($document),
        };

        file_put_contents($path, $content);
    }

    /**
     * 格式化檔案大小
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }
}
```

**使用方式**:

```bash
# 基本用法
php bin/console swagger:generate

# 指定輸出檔案
php bin/console swagger:generate -o public/openapi.json

# 生成 YAML 格式
php bin/console swagger:generate -f yaml -o public/openapi.yaml

# 僅生成 /api 開頭的路由
php bin/console swagger:generate --filter=/api

# 查看幫助
php bin/console swagger:generate --help
```

**整合到 CI/CD** (`.github/workflows/deploy.yml`):

```yaml
name: Deploy

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2

      - name: Install dependencies
        run: composer install --no-dev --optimize-autoloader

      - name: Generate OpenAPI documentation
        run: php bin/console swagger:generate -o public/api-doc.json

      - name: Commit generated docs
        run: |
          git config user.name "GitHub Actions"
          git config user.email "actions@github.com"
          git add public/api-doc.json
          git commit -m "chore: update API documentation" || true
          git push
```

---

## 三種方法比較

### 效能比較

| 方法 | 初始化時間 | 執行期開銷 | 記憶體使用 | 適合規模 |
|------|-----------|-----------|-----------|----------|
| **Compiler Pass** | 慢(僅編譯時) | 無 | 低 | 大型專案 |
| **Runtime Service** | 中(首次請求) | 中(有快取) | 中 | 中小型專案 |
| **Console Command** | 快(手動執行) | 無 | 低 | 任何規模 |

### 功能比較

| 功能 | Compiler Pass | Runtime Service | Console Command |
|------|--------------|----------------|-----------------|
| **即時更新** | ❌ 需清除快取 | ✅ 自動更新 | ❌ 需手動執行 |
| **動態路由** | ❌ 不支援 | ✅ 支援 | ⚠️ 部分支援 |
| **開發體驗** | ⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ |
| **生產效能** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **調試難度** | 高 | 低 | 中 |
| **CI/CD 整合** | ⭐⭐ | ⭐⭐ | ⭐⭐⭐⭐⭐ |

### 使用場景建議

#### 選擇 Compiler Pass 當:
- ✅ 路由完全靜態
- ✅ 追求極致效能
- ✅ 不介意清除快取

#### 選擇 Runtime Service 當:
- ✅ 需要即時看到變更
- ✅ 有動態路由
- ✅ 開發環境為主

#### 選擇 Console Command 當:
- ✅ 需要版本控制文檔
- ✅ 整合到 CI/CD
- ✅ 生成靜態文檔網站

---

## 混合策略(推薦)

在實際專案中,建議**結合多種方法**,根據環境自動選擇:

### 實作範例

```php
<?php

namespace SymfonySwagger\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class OpenApiGeneratorFactory
{
    public function __construct(
        #[Autowire('%kernel.environment%')]
        private string $environment,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
        private RouterInterface $router,
        private CacheItemPoolInterface $cache
    ) {}

    public function create(): OpenApiGeneratorInterface
    {
        return match($this->environment) {
            'prod' => $this->createStaticGenerator(),
            'dev' => $this->createRuntimeGenerator(),
            'test' => $this->createRuntimeGenerator(),
            default => $this->createRuntimeGenerator(),
        };
    }

    /**
     * 生產環境:使用預先生成的靜態檔案
     */
    private function createStaticGenerator(): OpenApiGeneratorInterface
    {
        $filePath = $this->projectDir . '/public/api-doc.json';

        return new StaticFileGenerator($filePath);
    }

    /**
     * 開發/測試環境:使用 Runtime 動態生成+快取
     */
    private function createRuntimeGenerator(): OpenApiGeneratorInterface
    {
        $ttl = $this->environment === 'dev' ? 60 : 3600;  // dev: 1分鐘, test: 1小時

        return new RuntimeOpenApiGenerator(
            $this->router,
            $this->cache,
            $ttl
        );
    }
}
```

### 配置範例

**config/services.yaml**:

```yaml
services:
    SymfonySwagger\Service\OpenApiGeneratorInterface:
        factory: ['@SymfonySwagger\Service\OpenApiGeneratorFactory', 'create']
```

**部署腳本**:

```bash
#!/bin/bash

# 生產環境部署前生成靜態文檔
if [ "$APP_ENV" = "prod" ]; then
    php bin/console swagger:generate -o public/api-doc.json
fi

# 清除快取
php bin/console cache:clear --env=prod
```

---

## NelmioApiDocBundle 實作分析

### 核心架構

NelmioApiDocBundle 主要使用 **Runtime Service + Caching** 策略:

1. **RouteDescriber**:負責從路由擷取資訊
2. **ModelDescriber**:負責分析 DTO 類別並生成 Schema
3. **OperationDescriber**:負責從 Attributes 擷取操作定義
4. **Caching**:使用 Symfony Cache 快取生成結果

### 關鍵類別

- `ApiDocGenerator`:主要生成器
- `RouteDescriberInterface`:路由分析介面
- `ModelRegistry`:管理所有 DTO Schema
- `OpenApiFactory`:建構 OpenAPI 規範物件

### 參考價值

✅ **Describer 模式**:將不同資訊源分離處理
✅ **Registry 模式**:集中管理可重用的 Schema
✅ **Provider 模式**:支援多種 Attribute 來源(Symfony, OpenAPI, 自訂)

---

## 總結與建議

### 最佳實踐

1. **開發階段**:使用 **Runtime Service** with short TTL cache
2. **測試階段**:使用 **Runtime Service** with medium TTL cache
3. **生產階段**:使用 **Console Command** 生成靜態檔案

### 技術決策

✅ **主要採用 Runtime Service 方式**
- 易於開發和調試
- 支援即時更新
- 透過多層快取優化效能

✅ **提供 Console Command 作為替代方案**
- 用於 CI/CD 整合
- 生成靜態文檔
- 版本控制

✅ **不使用 Compiler Pass 作為主要方式**
- 實作複雜度高
- 開發體驗差
- 無法完整取得 RouteCollection

### 後續實作重點

1. **實作 Runtime Service** 基礎架構
2. **整合 Symfony Cache** 實現多層快取
3. **實作 Console Command** 用於 CI/CD
4. **參考 NelmioApiDocBundle** 的 Describer 模式
5. **建立完整的 Reflection 分析工具**

---

## 參考資源

- [Symfony Compiler Passes Documentation](https://symfony.com/doc/current/service_container/compiler_passes.html)
- [Symfony Routing Component](https://symfony.com/doc/current/components/routing.html)
- [PHP Reflection API](https://www.php.net/manual/en/book.reflection.php)
- [NelmioApiDocBundle Source Code](https://github.com/nelmio/NelmioApiDocBundle)
- [Symfony Console Commands](https://symfony.com/doc/current/console.html)
