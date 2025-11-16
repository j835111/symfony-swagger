# Symfony Swagger Bundle Documentation

歡迎使用 Symfony Swagger Bundle!

## 目錄

1. [簡介](#簡介)
2. [安裝](#安裝)
3. [設定](#設定)
4. [使用方式](#使用方式)
5. [進階功能](#進階功能)

## 簡介

Symfony Swagger Bundle 是一個為 Symfony 應用程式自動產生 OpenAPI 3.0 規格文件的套件。

### 主要特色

- 自動產生符合 OpenAPI 3.0 標準的 API 文件
- 支援 PHP 8.1+ Attributes
- 完整的設定選項
- 與 Symfony 6/7 框架深度整合
- 遵循 Symfony Bundle 最佳實踐

## 安裝

### 系統需求

- PHP >= 8.1
- Symfony >= 6.0

### 使用 Composer 安裝

```bash
composer require your-vendor/symfony-swagger-bundle
```

### 註冊 Bundle

如果你沒有使用 Symfony Flex,需要手動在 `config/bundles.php` 中註冊:

```php
return [
    // ...
    SymfonySwagger\SymfonySwaggerBundle::class => ['all' => true],
];
```

## 設定

建立設定檔 `config/packages/symfony_swagger.yaml`:

```yaml
symfony_swagger:
    # 是否啟用 Bundle
    enabled: true

    # API 基本資訊
    info:
        title: 'My API'
        description: 'API Documentation'
        version: '1.0.0'

    # 伺服器設定
    servers:
        - url: 'https://api.example.com'
          description: 'Production server'
        - url: 'https://staging-api.example.com'
          description: 'Staging server'

    # 輸出檔案路徑
    output_path: '%kernel.project_dir%/public/swagger.json'
```

## 使用方式

### 基本使用

在你的 Controller 中注入 `SwaggerGenerator` 服務:

```php
use SymfonySwagger\Service\SwaggerGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;

class ApiDocController
{
    public function __construct(
        private SwaggerGenerator $swaggerGenerator
    ) {
    }

    public function documentation(): JsonResponse
    {
        $swagger = $this->swaggerGenerator->generate();

        return new JsonResponse($swagger);
    }
}
```

## 進階功能

更多進階功能即將推出,包括:

- PHP Attributes 支援
- 自動路由掃描
- Schema 自動產生
- 安全性設定
- 自訂 UI 整合

## 支援

如有問題或建議,請在 GitHub 上開啟 Issue。

## 授權

本專案使用 MIT 授權。
