# Symfony Swagger Bundle

**語言：** [English](README.md) | 繁體中文

這是一個 Symfony bundle，可從 Symfony controller attributes 自動產生 Swagger/OpenAPI 3.1 文件。

## 功能特色

- 自動產生 OpenAPI 3.1 規格文件。
- 讀取 Symfony controller attributes，例如 `#[Route]`、`#[MapRequestPayload]`、`#[MapQueryParameter]`、`#[MapQueryString]`、`#[MapUploadedFile]`。
- 從 DTO、union types、nullable types、enum、docblock、Doctrine ORM attributes、JMS serializer type hints 產生 schema。
- 內建 OpenAPI JSON、Swagger UI、Scalar API Reference 文件端點。
- 支援 request-level cache 與 Symfony Cache。
- 偵測循環 schema 參照。
- 針對使用 `#[IsGranted]` 的 endpoint 加入 security schemes。
- 可使用 `#[ApiResponse]` 自訂 response metadata。

## 系統需求

- PHP >= 8.2
- Symfony ^6.0 或 ^7.0

## 安裝

使用 Composer 安裝：

```bash
composer require j835111/symfony-swagger-bundle
```

Bundle 會自動：

- 載入預設設定，因此設定檔是選填的；
- 註冊內建文件路由：
  - `/api/docs.json`
  - `/api/docs`
  - `/api/docs/scalar`

如果你沒有使用 Symfony Flex，請手動註冊 bundle：

```php
// config/bundles.php
return [
    // ...
    SymfonySwagger\SymfonySwaggerBundle::class => ['all' => true],
];
```

## 設定

Bundle 預設即可運作。若需要自訂設定，建立 `config/packages/symfony_swagger.yaml`：

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

    output_path: '%kernel.project_dir%/public/openapi.json'
    generation_mode: runtime

    cache:
        enabled: true
        ttl: 3600

    analysis:
        max_depth: 5
        include_internal_routes: false

    security:
        enabled: true
        default_scheme: defaultAuth
        security_schemes:
            defaultAuth:
                type: http
                scheme: bearer
```

## 使用方式

安裝後，文件端點會立即可用：

```bash
curl https://your-app.example/api/docs.json
```

也可以在瀏覽器開啟互動式文件：

- Swagger UI：`https://your-app.example/api/docs`
- Scalar API Reference：`https://your-app.example/api/docs/scalar`

## 自訂文件 Controller

如果需要自訂文件 endpoint，可以在自己的 controller 注入 `OpenApiGenerator`：

```php
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use SymfonySwagger\Service\OpenApiGenerator;

final class DocumentationController
{
    public function __construct(
        private readonly OpenApiGenerator $openApiGenerator,
    ) {
    }

    #[Route('/internal/openapi.json', methods: ['GET'])]
    public function documentation(): JsonResponse
    {
        return new JsonResponse($this->openApiGenerator->generate());
    }
}
```

## Controller 範例

Bundle 會分析 Symfony controller attributes 與 method signature：

```php
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class PostController
{
    #[Route('/api/posts', methods: ['GET'])]
    public function list(
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] int $limit = 10,
    ): PostCollection {
        // 產生：
        // - path: /api/posts
        // - method: GET
        // - query parameters: page, limit
        // - response schema: PostCollection
    }

    #[Route('/api/posts', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreatePostDto $dto,
    ): Post {
        // 產生：
        // - requestBody schema: CreatePostDto
        // - response schema: Post
    }

    #[Route('/api/posts/{id}', methods: ['GET'])]
    public function show(int $id): Post
    {
        // 產生：
        // - path parameter: id
        // - response schema: Post
    }
}
```

## Response Metadata

當 return type 不足以描述 API response 時，可以使用 `#[ApiResponse]`：

```php
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use SymfonySwagger\Attribute\ApiResponse;

final class UserController
{
    #[Route('/api/users/{id}', methods: ['GET'])]
    #[ApiResponse(type: UserResponse::class)]
    public function show(string $id): JsonResponse
    {
        // Response envelope:
        // { code: int, message: string, data: UserResponse }
    }

    #[Route('/api/users/export', methods: ['GET'])]
    #[ApiResponse(file: true, fileMediaType: 'text/csv')]
    public function export(): BinaryFileResponse
    {
        // 產生 binary file response schema。
    }
}
```

## DTO 範例

```php
final class CreatePostDto
{
    public string $title;
    public string $content;
    public ?string $excerpt = null;
    public Status $status;
    public AuthorDto $author;

    /** @var string[] */
    public array $tags;
}

enum Status: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
}
```

產生的 schema 會包含 required fields、nullable fields、enum values、巢狀 DTO references，以及從 docblock 推導出的 array item types。

## 開發

安裝相依套件：

```bash
composer install
```

執行測試：

```bash
vendor/bin/phpunit
```

執行靜態分析與程式碼風格檢查：

```bash
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix --dry-run --diff
```

或執行專案分析 script：

```bash
composer analyze
```

## 目錄結構

```text
symfony-swagger/
├── src/
│   ├── SymfonySwaggerBundle.php
│   ├── DependencyInjection/
│   ├── Controller/
│   ├── Routing/
│   ├── Service/
│   ├── Analyzer/
│   └── Attribute/
├── config/
│   ├── packages/
│   ├── routes/
│   └── services.php
├── tests/
├── docs/
└── composer.json
```

## 貢獻

歡迎提交 issue 和 pull request。

## 授權

MIT License

## 相關連結

- [Symfony Documentation](https://symfony.com/doc/current/index.html)
- [OpenAPI Specification](https://swagger.io/specification/)
- [Symfony Bundle Best Practices](https://symfony.com/doc/current/bundles/best_practices.html)
