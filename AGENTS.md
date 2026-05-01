# PROJECT KNOWLEDGE BASE

**Generated:** 2026-05-01
**Commit:** f306196
**Branch:** main

## OVERVIEW
Symfony bundle for automatic Swagger/OpenAPI 3.1 documentation generation from Symfony controller attributes. Supports PHP >=8.2 with Symfony 6/7.

## STRUCTURE
```
symfony-swagger/
├── src/
│   ├── SymfonySwaggerBundle.php     # Bundle entry and default config prepend
│   ├── DependencyInjection/          # Config tree and extension
│   ├── Controller/                   # Built-in docs/UI endpoints
│   ├── Routing/                      # AutoRouteLoader for built-in routes
│   ├── Service/                      # OpenAPI generator, describers, registry
│   ├── Analyzer/                     # Attribute, type, docblock, Doctrine helpers
│   └── Attribute/                    # Custom PHP attributes
├── config/
│   ├── services.php                  # DI configuration, PHP style
│   ├── packages/symfony_swagger.yaml # Optional bundle config
│   └── routes/symfony_swagger.php    # Built-in docs routes
├── tests/
│   ├── Analyzer/
│   ├── Bundle/
│   ├── Config/
│   ├── Controller/
│   ├── DependencyInjection/
│   ├── Routing/
│   └── Service/
├── .github/workflows/ci.yml          # CI matrix
├── phpunit.xml.dist                  # PHPUnit config
└── .php-cs-fixer.dist.php            # Code style config
```

## WHERE TO LOOK
| Task | Location | Notes |
|------|----------|-------|
| Bundle entry | `src/SymfonySwaggerBundle.php` | Main bundle class; prepends defaults |
| Config schema | `src/DependencyInjection/Configuration.php` | `symfony_swagger` config tree |
| DI wiring | `config/services.php` | PHP-style service definitions |
| Built-in routes | `config/routes/symfony_swagger.php` | `/api/docs.json`, `/api/docs`, `/api/docs/scalar` |
| Automatic route injection | `src/Routing/AutoRouteLoader.php` | Decorates Symfony routing loader |
| OpenAPI generation | `src/Service/OpenApiGenerator.php` | Core orchestrator |
| Route reading | `src/Service/Describer/RouteDescriber.php` | Route parsing and filtering |
| Operation generation | `src/Service/Describer/OperationDescriber.php` | Request/response/parameter operation details |
| Schema generation | `src/Service/Describer/SchemaDescriber.php` | DTO schema output |
| Schema registry | `src/Service/Registry/SchemaRegistry.php` | Component schema storage |
| Type analysis | `src/Analyzer/TypeAnalyzer.php` | DTO/property type inference |
| Attribute reading | `src/Analyzer/AttributeReader.php` | Symfony and bundle PHP attributes |
| Backward-compatible generator | `src/Service/SwaggerGenerator.php` | Deprecated wrapper around `OpenApiGenerator` |
| Run tests | `vendor/bin/phpunit` | PHPUnit 10.x |
| Code style | `vendor/bin/php-cs-fixer fix` | PSR-12/Symfony style |
| Static analysis | `vendor/bin/phpstan analyse` | PHPStan |

## CONVENTIONS (THIS PROJECT)
- **PSR-4**: `SymfonySwagger\` -> `src/`, `SymfonySwagger\Tests\` -> `tests/`.
- **PHP**: >=8.2, with `declare(strict_types=1);`.
- **Symfony**: Composer allows Symfony `^6.0|^7.0`; CI currently tests Symfony `6.4.*` and `7.0.*`.
- **CI PHP matrix**: PHP `8.2` and `8.3` only; PHP 8.1 is not supported.
- **Config**: PHP-style service and route files. Bundle config key is `symfony_swagger`.
- **Routing**: Prefer PHP attributes such as `#[Route]`, `#[MapRequestPayload]`, `#[MapQueryParameter]`, `#[MapQueryString]`, and `#[MapUploadedFile]`.
- **Responses**: Use `SymfonySwagger\Attribute\ApiResponse` for explicit response DTO/file metadata.
- **Tests**: PHPUnit 10.x, `*Test.php` naming, `test*()` methods.
- **Composer lock**: `composer.lock` is ignored because this is a library package.

## ANTI-PATTERNS (THIS PROJECT)
- No `index.php` or `bin/console`; this is a reusable bundle, not an app.
- No YAML routing for bundle internals; route/config definitions are PHP files.
- Do not add Doctrine annotation parsing as the primary path; use PHP attributes/reflection.
- No Behat; use PHPUnit.
- Do not broaden Symfony/PHP support in code or CI without updating `composer.json`, CI, and this file together.

## COMMANDS
```bash
composer install
composer validate --strict --no-check-publish
vendor/bin/phpunit
vendor/bin/php-cs-fixer fix --dry-run --diff
vendor/bin/phpstan analyse
composer analyze
```

## NOTES
- Built-in docs endpoints: `/api/docs.json`, `/api/docs`, `/api/docs/scalar`.
- Default output path: `%kernel.project_dir%/public/swagger.json`.
- `OpenApiGenerator` uses Symfony `RouterInterface` and can use Symfony Cache as L2 cache.
- `AutoRouteLoader` injects built-in documentation routes when `symfony_swagger.enabled` is true.
- DTO analysis supports union types, nullable types, enums, docblocks, Doctrine ORM attributes, and JMS serializer type hints.
