# Symfony Swagger Bundle

一個用於 Symfony 應用程式的 Swagger/OpenAPI 文件自動產生套件。

## 功能特色

- ✅ 自動產生 OpenAPI 3.1 規格文件
- ✅ 從 Symfony Controller Attributes 自動生成文檔
- ✅ 支援 PHP 8.1+ Attributes (Route, MapRequestPayload, MapQueryParameter 等)
- ✅ 智能型別分析 (支援 DTO、Union Types、Nullable Types、Enum)
- ✅ 多層快取機制 (Request + Symfony Cache)
- ✅ Schema 自動生成與循環引用偵測
- ✅ 與 Symfony 6/7 完美整合
- ✅ 遵循 Symfony Bundle 最佳實踐

## 系統需求

- PHP >= 8.1
- Symfony >= 6.0

## 安裝

使用 Composer 安裝套件:

```bash
composer require your-vendor/symfony-swagger-bundle
```

如果你沒有使用 Symfony Flex,需要手動註冊 Bundle:

```php
// config/bundles.php
return [
    // ...
    SymfonySwagger\SymfonySwaggerBundle::class => ['all' => true],
];
```

## 設定

建立設定檔 `config/packages/symfony_swagger.yaml`:

```yaml
symfony_swagger:
    enabled: true

    info:
        title: 'My API'
        description: 'API Documentation'
        version: '1.0.0'

    servers:
        - url: 'https://api.example.com'
          description: 'Production server'
        - url: 'https://staging-api.example.com'
          description: 'Staging server'

    output_path: '%kernel.project_dir%/public/swagger.json'

    # 快取設定
    cache:
        enabled: true
        ttl: 3600  # 快取時間(秒)

    # 分析設定
    analysis:
        max_depth: 5  # DTO 遞迴分析最大深度
        include_internal_routes: false  # 是否包含內部路由 (_profiler 等)
```

## 使用方式

### 基本使用

```php
use SymfonySwagger\Service\OpenApiGenerator;

class DocumentationController
{
    public function __construct(
        private OpenApiGenerator $openApiGenerator
    ) {
    }

    #[Route('/api/docs.json', methods: ['GET'])]
    public function documentation(): Response
    {
        $openApiDoc = $this->openApiGenerator->generate();

        return $this->json($openApiDoc);
    }
}
```

### Controller 範例

Bundle 會自動從 Symfony Controller Attributes 生成文檔:

```php
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

class PostController
{
    #[Route('/api/posts', methods: ['GET'])]
    public function list(
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] int $limit = 10
    ): PostCollection {
        // 自動生成 OpenAPI:
        // - path: /api/posts
        // - method: GET
        // - parameters: page (query, integer), limit (query, integer)
        // - response: PostCollection schema
    }

    #[Route('/api/posts', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreatePostDto $dto
    ): Post {
        // 自動生成 OpenAPI:
        // - requestBody: CreatePostDto schema
        // - response: Post schema
    }

    #[Route('/api/posts/{id}', methods: ['GET'])]
    public function show(int $id): Post
    {
        // 自動生成 OpenAPI:
        // - path parameter: id (integer)
        // - response: Post schema
    }
}
```

### DTO 範例

```php
class CreatePostDto
{
    public string $title;
    public string $content;
    public ?string $excerpt = null;
    public Status $status;  // Enum 支援
    public AuthorDto $author;  // 巢狀物件

    /** @var string[] */
    public array $tags;  # 從 DocBlock 推導陣列元素型別
}

enum Status: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
}

// 自動生成的 Schema:
// - CreatePostDto (object)
//   - title (string, required)
//   - content (string, required)
//   - excerpt (string, nullable)
//   - status (string, enum: [draft, published])
//   - author ($ref: #/components/schemas/AuthorDto)
//   - tags (array of string)
```

## 開發

### 執行測試

```bash
composer install
vendor/bin/phpunit
```

### 程式碼格式檢查

```bash
vendor/bin/php-cs-fixer fix
```

## 目錄結構

```
symfony-swagger/
├── src/
│   ├── SymfonySwaggerBundle.php
│   ├── DependencyInjection/
│   │   ├── Configuration.php
│   │   └── SymfonySwaggerExtension.php
│   ├── Service/
│   │   └── SwaggerGenerator.php
│   ├── Attribute/
│   └── Command/
├── config/
│   └── services.php
├── tests/
├── docs/
└── composer.json
```

## 貢獻

歡迎提交 Issue 和 Pull Request!

## 授權

MIT License

## 相關連結

- [Symfony Documentation](https://symfony.com/doc/current/index.html)
- [OpenAPI Specification](https://swagger.io/specification/)
- [Symfony Bundle Best Practices](https://symfony.com/doc/current/bundles/best_practices.html)
