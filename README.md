# Symfony Swagger Bundle

**Language:** English | [繁體中文](README.zh-TW.md)

A Symfony bundle that automatically generates Swagger/OpenAPI 3.1 documentation from Symfony controller attributes.

## Features

- Generate OpenAPI 3.1 documents automatically.
- Read Symfony controller attributes such as `#[Route]`, `#[MapRequestPayload]`, `#[MapQueryParameter]`, `#[MapQueryString]`, and `#[MapUploadedFile]`.
- Generate schemas from DTOs, union types, nullable types, enums, docblocks, Doctrine ORM attributes, and JMS serializer type hints.
- Provide built-in documentation endpoints for OpenAPI JSON, Swagger UI, and Scalar API Reference.
- Support request-level caching and Symfony Cache.
- Detect circular schema references.
- Add security schemes for endpoints using `#[IsGranted]`.
- Customize response metadata with `#[ApiResponse]`.

## Requirements

- PHP >= 8.2
- Symfony ^6.0 or ^7.0

## Installation

Install the bundle with Composer:

```bash
composer require j835111/symfony-swagger-bundle
```

The bundle automatically:

- loads default configuration, so a config file is optional;
- registers built-in documentation routes:
  - `/api/docs.json`
  - `/api/docs`
  - `/api/docs/scalar`

If you are not using Symfony Flex, register the bundle manually:

```php
// config/bundles.php
return [
    // ...
    SymfonySwagger\SymfonySwaggerBundle::class => ['all' => true],
];
```

## Configuration

The bundle works with defaults out of the box. To customize it, create `config/packages/symfony_swagger.yaml`:

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

## Usage

After installation, the documentation endpoints are available immediately:

```bash
curl https://your-app.example/api/docs.json
```

Open the interactive documentation pages in a browser:

- Swagger UI: `https://your-app.example/api/docs`
- Scalar API Reference: `https://your-app.example/api/docs/scalar`

## Custom Documentation Controller

If you need a custom endpoint, inject `OpenApiGenerator` into your own controller:

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

## Controller Example

The bundle inspects Symfony controller attributes and method signatures:

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
        // Generates:
        // - path: /api/posts
        // - method: GET
        // - query parameters: page, limit
        // - response schema: PostCollection
    }

    #[Route('/api/posts', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreatePostDto $dto,
    ): Post {
        // Generates:
        // - requestBody schema: CreatePostDto
        // - response schema: Post
    }

    #[Route('/api/posts/{id}', methods: ['GET'])]
    public function show(int $id): Post
    {
        // Generates:
        // - path parameter: id
        // - response schema: Post
    }
}
```

## Response Metadata

Use `#[ApiResponse]` when the return type is not enough to describe the API response:

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
        // Generates a binary file response schema.
    }
}
```

## DTO Example

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

The generated schema includes required fields, nullable fields, enum values, nested DTO references, and array item types inferred from docblocks.

## Development

Install dependencies:

```bash
composer install
```

Run tests:

```bash
vendor/bin/phpunit
```

Run static analysis and style checks:

```bash
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix --dry-run --diff
```

Or run the project analysis script:

```bash
composer analyze
```

## Project Structure

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

## Contributing

Issues and pull requests are welcome.

## License

MIT License

## Links

- [Symfony Documentation](https://symfony.com/doc/current/index.html)
- [OpenAPI Specification](https://swagger.io/specification/)
- [Symfony Bundle Best Practices](https://symfony.com/doc/current/bundles/best_practices.html)
