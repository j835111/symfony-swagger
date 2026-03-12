# PROJECT KNOWLEDGE BASE

**Generated:** 2026-03-12
**Commit:** 2035185
**Branch:** main

## OVERVIEW
Symfony Bundle for automatic Swagger/OpenAPI documentation generation from Symfony Controller Attributes. Supports PHP 8.1+ with Symfony 6/7.

## STRUCTURE
```
symfony-swagger/
├── src/
│   ├── SymfonySwaggerBundle.php    # Bundle entry
│   ├── DependencyInjection/         # Configuration tree
│   ├── Service/                    # OpenApiGenerator, Describer, Registry
│   ├── Analyzer/                   # AttributeReader, TypeAnalyzer
│   └── Attribute/                  # Custom attributes
├── config/
│   └── services.php                # DI configuration (PHP style)
├── tests/
│   ├── Analyzer/                   # Unit tests
│   └── Service/                    # Service tests
├── .github/workflows/ci.yml        # CI pipeline
├── phpunit.xml.dist                # Test config
├── .php-cs-fixer.dist.php         # Code style
└── openspec/                      # Change proposals
```

## WHERE TO LOOK
| Task | Location | Notes |
|------|----------|-------|
| Bundle entry | `src/SymfonySwaggerBundle.php` | Main class |
| Config schema | `src/DependencyInjection/Configuration.php` | YAML config tree |
| OpenAPI generation | `src/Service/OpenApiGenerator.php` | Core orchestrator |
| Route reading | `src/Service/Describer/RouteDescriber.php` | Route parsing |
| Type analysis | `src/Analyzer/TypeAnalyzer.php` | DTO type inference |
| Attribute reading | `src/Analyzer/AttributeReader.php` | PHP 8.1 Attributes |
| Run tests | `vendor/bin/phpunit` | PHPUnit 10.x |
| Code style | `vendor/bin/php-cs-fixer fix` | PSR-12 + Symfony |
| Static analysis | `phpstan analyse` | PHPStan |

## CONVENTIONS (THIS PROJECT)
- **PSR-4**: `SymfonySwagger\` → `src/`, `SymfonySwagger\Tests\` → `tests/`
- **PHP**: >=8.1 with strict_types
- **Config**: PHP-style services.php (not YAML)
- **Routing**: PHP 8.1 Attributes (#[Route], #[MapRequestPayload], #[MapQueryParameter])
- **Tests**: PHPUnit 10.x, `*Test.php` naming, `test*()` methods

## ANTI-PATTERNS (THIS PROJECT)
- No `index.php` or `bin/console` (library, not app)
- No YAML routing (uses Attributes)
- No Doctrine annotations
- No Behat (PHPUnit only)

## COMMANDS
```bash
composer install
vendor/bin/phpunit
vendor/bin/php-cs-fixer fix
phpstan analyse
composer analyze
```

## NOTES
- Bundle config: `config/packages/symfony_swagger.yaml`
- Output: JSON file via OpenApiGenerator service
- Uses Symfony's RouterInterface to read routes
- Supports DTO analysis with union types, nullable, enums
