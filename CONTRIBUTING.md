# Contributing to Symfony Swagger Bundle

First off, thanks for taking the time to contribute!

## Reporting Bugs

If you find a bug, please report it using the GitHub issue tracker.
Be sure to include:
- Symfony version
- PHP version
- Bundle version
- Reproduction steps or code snippet

## Pull Requests

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## Coding Standards

This project follows [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standards.
Please run the following command before committing:

```bash
composer run-script analyze
```

This will run PHPStan and PHP-CS-Fixer.

## Running Tests

Please ensure all tests pass:

```bash
vendor/bin/phpunit
```
