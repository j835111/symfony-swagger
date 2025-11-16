<!-- OPENSPEC:START -->
# OpenSpec Instructions

These instructions are for AI assistants working in this project.

Always open `@/openspec/AGENTS.md` when the request:
- Mentions planning or proposals (words like proposal, spec, change, plan)
- Introduces new capabilities, breaking changes, architecture shifts, or big performance/security work
- Sounds ambiguous and you need the authoritative spec before coding

Use `@/openspec/AGENTS.md` to learn:
- How to create and apply change proposals
- Spec format and conventions
- Project structure and guidelines

Keep this managed block so 'openspec update' can refresh the instructions.

<!-- OPENSPEC:END -->

# Symfony Bundle 最佳實踐

## 核心原則

### 1. 使用時機
- Bundle **僅應用於**在多個應用程式之間共享程式碼和功能
- Symfony 4.0 之後,**不再建議**使用 Bundle 來組織應用程式程式碼
- 對於一般應用程式程式碼,應使用 PHP 命名空間來組織

### 2. 命名規範
- 命名空間必須遵循 **PSR-4** 標準
- 結構:供應商(vendor) → 分類(category,可選) → Bundle 名稱
- Bundle 類別名稱應使用 StudlyCaps,簡短描述性(不超過兩個字)
- 前綴使用供應商名稱,後綴必須是 `Bundle`

**範例:**
```
Vendor\CategoryBundle\VendorCategoryBundle
Acme\BlogBundle\AcmeBlogBundle
```

### 3. 目錄結構
```
YourBundle/
├── src/
│   └── YourBundle.php          # Bundle 主類別
├── tests/                       # PHPUnit 測試套件
├── docs/
│   └── index.rst (或 index.md) # 必需的文件入口
├── config/                      # 設定檔案
└── composer.json               # Composer 設定
```

### 4. Composer 設定
- 在 `composer.json` 中使用 PSR-4 自動載入標準
- 主類別應位於 `src/` 目錄
- 必須在 **Packagist** 上註冊以便其他開發者發現

**composer.json 範例:**
```json
{
    "name": "vendor/bundle-name",
    "type": "symfony-bundle",
    "autoload": {
        "psr-4": {
            "Vendor\\BundleName\\": "src/"
        }
    },
    "require": {
        "php": ">=8.1",
        "symfony/framework-bundle": "^6.0|^7.0"
    }
}
```

### 5. 設定管理
- 對於簡單的設定,依賴 Symfony 的預設參數項目
- 每個參數名稱應以 bundle 別名開頭
- 使用 Configuration 類別定義設定結構
- 使用 Extension 類別載入設定

### 6. 檔案參照
- 使用實體路徑 (如 `__DIR__/config/services.xml`)
- **不再建議**使用邏輯路徑 (如 `@BundleName/Resources/config/services.xml`)

### 7. 測試
- 必須在 `tests/` 目錄下包含 **PHPUnit** 測試套件
- 實施**持續整合 (CI)**,建議使用 GitHub Actions
- 測試所有提交和 Pull Request

**CI 設定範例 (.github/workflows/tests.yml):**
```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2
      - name: Install dependencies
        run: composer install
      - name: Run tests
        run: vendor/bin/phpunit
```

### 8. 程式碼品質
- 遵循 **PSR-12** 編碼標準
- 使用 **PHP-CS-Fixer** 等自動化工具強制執行標準
- 定期進行程式碼審查
- 遵循 Symfony 編碼標準

**PHP-CS-Fixer 設定範例 (.php-cs-fixer.php):**
```php
<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__.'/src')
    ->in(__DIR__.'/tests')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        '@PSR12' => true,
    ])
    ->setFinder($finder)
;
```

### 9. 文件
- 所有類別和函式必須包含完整的 **PHPDoc**
- 在 `docs/` 目錄提供詳細文件
- `docs/index.rst` 或 `docs/index.md` 是必需的入口文件
- 說明安裝、設定和使用方式

**文件結構範例:**
```
docs/
├── index.md           # 入口文件
├── installation.md    # 安裝說明
├── configuration.md   # 設定說明
└── usage.md          # 使用範例
```

## Bundle 主類別範例

```php
<?php

namespace Vendor\BundleName;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class VendorBundleNameBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
```

## DependencyInjection 結構

### Extension 類別
```php
<?php

namespace Vendor\BundleName\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class VendorBundleNameExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.php');
    }
}
```

### Configuration 類別
```php
<?php

namespace Vendor\BundleName\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('vendor_bundle_name');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('api_key')->defaultValue('')->end()
                ->booleanNode('enabled')->defaultTrue()->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
```

## 服務定義

**config/services.php:**
```php
<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Vendor\BundleName\Service\YourService;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
    ;

    $services->set(YourService::class)
        ->public()
    ;
};
```

## 路由 (Routing) 最佳實踐

### 1. 使用 PHP 8 Attributes
從 Symfony 5.2 開始,**強烈建議**使用 PHP 8 原生 Attributes 定義路由,這是目前的標準做法。

### 2. 基本用法
```php
use Symfony\Component\Routing\Attribute\Route;

class BlogController extends AbstractController
{
    #[Route('/blog', name: 'blog_list')]
    public function list(): Response
    {
        // ...
    }
}
```

### 3. 常用功能

#### HTTP 方法限制
```php
#[Route('/api/posts/{id}', methods: ['GET', 'HEAD'])]
public function show(int $id): Response { }

#[Route('/api/posts/{id}', methods: ['PUT'])]
public function update(int $id): Response { }
```

#### 路由參數與驗證
```php
// 基本參數
#[Route('/blog/{slug}')]
public function show(string $slug): Response { }

// 參數驗證 (正規表達式)
#[Route('/blog/{page}', requirements: ['page' => '\d+'])]
public function list(int $page): Response { }

// 可選參數
#[Route('/blog/{page}')]
public function list(int $page = 1): Response { }
```

#### 自動參數轉換
```php
#[Route('/blog/{slug:post}')]
public function show(BlogPost $post): Response
{
    // $post 會自動從 slug 轉換為 BlogPost 物件
}
```

#### 路由群組與前綴
```php
#[Route('/api', name: 'api_')]
class ApiController extends AbstractController
{
    #[Route('/users', name: 'users')]  // 實際路徑: /api/users
    public function users(): Response { }

    #[Route('/posts', name: 'posts')]  // 實際路徑: /api/posts
    public function posts(): Response { }
}
```

#### 條件路由
```php
#[Route('/posts/{id}', condition: "params['id'] < 1000")]
public function show(int $id): Response { }
```

#### 環境限制
```php
#[Route('/debug-tools', name: 'tools', env: 'dev')]
public function tools(): Response
{
    // 此路由僅在開發環境中可用
}
```

### 4. 從 Annotations 遷移到 Attributes

**舊語法 (不建議):**
```php
/**
 * @Route("/path", name="action")
 */
public function someAction() {}
```

**新語法 (建議):**
```php
#[Route('/path', name: 'action')]
public function someAction() {}
```

**遷移注意事項:**
- 無需更改 `use` 導入語句
- 可使用 Rector 工具自動轉換
- 轉換後可移除 `doctrine/annotations` 依賴

### 5. 調試工具

```bash
# 列出所有路由
php bin/console debug:router

# 測試特定 URL
php bin/console router:match /blog/my-post
```

### 6. 特殊參數

- `_format` - 請求格式 (json, html, xml 等)
- `_locale` - 語言設定
- `_controller` - 指定執行的控制器

### 7. 優勢

- **原生 PHP** - 無需額外的註解解析器
- **效能更佳** - PHP 原生支援,無需額外解析步驟
- **更好的 IDE 支援** - 自動完成和型別檢查
- **清晰易讀** - 使用原生 PHP 語法而非 PHPDoc 註解

## 參考資源

- [Official Symfony Bundle Best Practices](https://symfony.com/doc/current/bundles/best_practices.html)
- [The Bundle System Documentation](https://symfony.com/doc/current/bundles.html)
- [Symfony Routing Documentation](https://symfony.com/doc/current/routing.html)
- [PHP 8 Attributes in Symfony](https://symfony.com/blog/new-in-symfony-5-2-php-8-attributes)
- [PSR-4 Autoloading Standard](https://www.php-fig.org/psr/psr-4/)
- [PSR-12 Coding Style](https://www.php-fig.org/psr/psr-12/)

---

# OpenAPI 規範與撰寫指南

## 概述

**OpenAPI** (前身為 Swagger) 是描述 RESTful API 的標準規範格式,使用 JSON 或 YAML 格式撰寫。

### 核心概念

- **OpenAPI** = 規範標準
- **Swagger** = 實作工具集 (Swagger UI, Swagger Editor 等)
- **格式** = JSON 或 YAML
- **目的** = 機器可讀的 API 描述文件

### 適用範圍

OpenAPI 描述包含:
- API 端點 (endpoints) 與 HTTP 方法
- 請求參數、請求體、回應格式
- 資料模型 (schemas)
- 認證與授權機制
- API 伺服器資訊與版本
- 錯誤處理定義

## 使用 OpenAPI 的優勢

1. **標準化文檔** - 統一的 API 描述格式,提升團隊協作效率
2. **自動生成工具** - 可生成客戶端 SDK、伺服器端骨架、測試程式
3. **互動式文檔** - 透過 Swagger UI 提供可測試的 API 文檔
4. **設計優先開發** - 先定義 API 規格再實作,減少後期調整
5. **Mock Server** - 前端可在後端開發前先使用 Mock API
6. **API 驗證** - 自動驗證 API 實作是否符合規格
7. **持續整合** - 整合到 CI/CD 流程,確保 API 一致性

## OpenAPI 3.1 基本結構

### 必要欄位

```yaml
openapi: 3.1.0  # OpenAPI 版本號 (必填)

info:           # API 基本資訊 (必填)
  title: API 名稱
  version: 1.0.0
  description: API 描述

# 至少需要以下其中一個
paths: {}       # API 端點定義
components: {}  # 可重用元件
webhooks: {}    # Webhook 定義
```

### 完整結構範例

```yaml
openapi: 3.1.0

info:
  title: Blog API
  version: 1.0.0
  description: 部落格文章管理 API
  contact:
    name: API Support Team
    email: support@example.com
    url: https://support.example.com
  license:
    name: Apache 2.0
    url: https://www.apache.org/licenses/LICENSE-2.0.html

servers:
  - url: https://api.example.com/v1
    description: 正式環境
  - url: https://staging-api.example.com/v1
    description: 測試環境
  - url: http://localhost:8000/v1
    description: 本地開發環境

tags:
  - name: posts
    description: 文章管理相關操作
  - name: users
    description: 使用者管理相關操作
  - name: comments
    description: 評論管理相關操作

paths:
  /posts:
    get:
      summary: 取得文章列表
      description: 取得所有文章,支援分頁和篩選
      operationId: listPosts
      tags:
        - posts
      parameters:
        - name: page
          in: query
          description: 頁碼 (從 1 開始)
          required: false
          schema:
            type: integer
            minimum: 1
            default: 1
            example: 1
        - name: limit
          in: query
          description: 每頁筆數
          required: false
          schema:
            type: integer
            minimum: 1
            maximum: 100
            default: 10
            example: 20
        - name: status
          in: query
          description: 文章狀態篩選
          schema:
            type: string
            enum: [draft, published, archived]
      responses:
        '200':
          description: 成功取得文章列表
          content:
            application/json:
              schema:
                type: object
                properties:
                  data:
                    type: array
                    items:
                      $ref: '#/components/schemas/Post'
                  pagination:
                    $ref: '#/components/schemas/Pagination'
              example:
                data:
                  - id: 1
                    title: OpenAPI 入門教學
                    content: 這是一篇關於 OpenAPI 的文章...
                    status: published
                    author:
                      id: 101
                      name: 張三
                    createdAt: '2025-01-15T10:30:00Z'
                pagination:
                  page: 1
                  limit: 10
                  total: 50
                  totalPages: 5
        '400':
          $ref: '#/components/responses/BadRequest'
        '500':
          $ref: '#/components/responses/InternalServerError'

    post:
      summary: 建立新文章
      description: 建立一篇新的部落格文章
      operationId: createPost
      tags:
        - posts
      security:
        - bearerAuth: []
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/PostInput'
            example:
              title: 我的新文章
              content: 這是文章內容...
              status: draft
      responses:
        '201':
          description: 文章建立成功
          headers:
            Location:
              description: 新建立的文章 URL
              schema:
                type: string
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Post'
        '400':
          $ref: '#/components/responses/BadRequest'
        '401':
          $ref: '#/components/responses/Unauthorized'
        '422':
          $ref: '#/components/responses/ValidationError'

  /posts/{id}:
    parameters:
      - name: id
        in: path
        required: true
        description: 文章 ID
        schema:
          type: integer
          minimum: 1
        example: 42

    get:
      summary: 取得單一文章
      description: 根據 ID 取得特定文章的詳細資訊
      operationId: getPost
      tags:
        - posts
      responses:
        '200':
          description: 成功取得文章
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Post'
        '404':
          $ref: '#/components/responses/NotFound'

    put:
      summary: 更新文章
      description: 更新指定文章的內容
      operationId: updatePost
      tags:
        - posts
      security:
        - bearerAuth: []
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/PostInput'
      responses:
        '200':
          description: 更新成功
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Post'
        '401':
          $ref: '#/components/responses/Unauthorized'
        '404':
          $ref: '#/components/responses/NotFound'
        '422':
          $ref: '#/components/responses/ValidationError'

    delete:
      summary: 刪除文章
      description: 刪除指定的文章
      operationId: deletePost
      tags:
        - posts
      security:
        - bearerAuth: []
      responses:
        '204':
          description: 刪除成功 (無內容)
        '401':
          $ref: '#/components/responses/Unauthorized'
        '404':
          $ref: '#/components/responses/NotFound'

components:
  schemas:
    Post:
      type: object
      required:
        - id
        - title
        - content
        - status
      properties:
        id:
          type: integer
          description: 文章唯一識別碼
          example: 1
        title:
          type: string
          description: 文章標題
          minLength: 1
          maxLength: 200
          example: OpenAPI 入門教學
        content:
          type: string
          description: 文章內容 (支援 Markdown)
          minLength: 1
          example: 這是一篇關於 OpenAPI 的詳細教學...
        status:
          type: string
          description: 文章狀態
          enum: [draft, published, archived]
          default: draft
          example: published
        author:
          $ref: '#/components/schemas/User'
        tags:
          type: array
          description: 文章標籤
          items:
            type: string
          example: [openapi, api, tutorial]
        createdAt:
          type: string
          format: date-time
          description: 建立時間 (ISO 8601 格式)
          example: '2025-01-15T10:30:00Z'
        updatedAt:
          type: string
          format: date-time
          description: 最後更新時間
          example: '2025-01-16T14:20:00Z'

    PostInput:
      type: object
      required:
        - title
        - content
      properties:
        title:
          type: string
          description: 文章標題
          minLength: 1
          maxLength: 200
        content:
          type: string
          description: 文章內容
          minLength: 1
        status:
          type: string
          description: 文章狀態
          enum: [draft, published, archived]
          default: draft
        tags:
          type: array
          items:
            type: string
          maxItems: 10

    User:
      type: object
      properties:
        id:
          type: integer
          description: 使用者 ID
          example: 101
        name:
          type: string
          description: 使用者名稱
          example: 張三
        email:
          type: string
          format: email
          description: 電子郵件地址
          example: zhang@example.com
        avatar:
          type: string
          format: uri
          description: 頭像 URL
          example: https://example.com/avatars/101.jpg

    Pagination:
      type: object
      required:
        - page
        - limit
        - total
      properties:
        page:
          type: integer
          description: 當前頁碼
          minimum: 1
          example: 1
        limit:
          type: integer
          description: 每頁筆數
          minimum: 1
          example: 10
        total:
          type: integer
          description: 總筆數
          minimum: 0
          example: 50
        totalPages:
          type: integer
          description: 總頁數
          minimum: 0
          example: 5

    Error:
      type: object
      required:
        - code
        - message
      properties:
        code:
          type: string
          description: 錯誤代碼
          example: VALIDATION_ERROR
        message:
          type: string
          description: 錯誤訊息
          example: 標題欄位為必填
        details:
          type: array
          description: 詳細錯誤資訊
          items:
            type: object
            properties:
              field:
                type: string
                example: title
              message:
                type: string
                example: 標題不可為空

  responses:
    BadRequest:
      description: 錯誤的請求格式
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/Error'
          example:
            code: BAD_REQUEST
            message: 請求格式錯誤

    Unauthorized:
      description: 未授權 - 需要有效的認證資訊
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/Error'
          example:
            code: UNAUTHORIZED
            message: 請提供有效的認證 token

    NotFound:
      description: 找不到指定的資源
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/Error'
          example:
            code: NOT_FOUND
            message: 找不到指定的文章

    ValidationError:
      description: 資料驗證失敗
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/Error'
          example:
            code: VALIDATION_ERROR
            message: 資料驗證失敗
            details:
              - field: title
                message: 標題長度必須在 1-200 字元之間
              - field: content
                message: 內容不可為空

    InternalServerError:
      description: 伺服器內部錯誤
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/Error'
          example:
            code: INTERNAL_ERROR
            message: 伺服器發生錯誤,請稍後再試

  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
      bearerFormat: JWT
      description: JWT Bearer Token 認證

    apiKey:
      type: apiKey
      in: header
      name: X-API-Key
      description: API Key 認證

# 全域安全設定 (可被個別端點覆蓋)
security:
  - bearerAuth: []
```

## OpenAPI 最佳實踐

### 1. 設計優先 (Design-First Approach)

**強烈建議**先撰寫 OpenAPI 規格,再實作程式碼。

✅ **優點:**
- 清晰的架構規劃
- 團隊成員對齊 API 設計目標
- 前後端可並行開發 (使用 Mock Server)
- 避免開發後期難以調整的問題
- 更容易發現設計缺陷

❌ **避免:**
- 從現有程式碼反向生成 (Code-First)
- 文檔與實作不一致

### 2. 單一資訊源 (Single Source of Truth)

OpenAPI 文件應該是 API 的唯一真實來源。

**實踐方式:**
- 將 OpenAPI 檔案納入版本控制 (Git)
- 整合到 CI/CD 流程
- 使用自動化測試驗證實作與規格的一致性
- 避免在多處重複定義相同資訊

```yaml
# ✅ 好的做法 - 使用 $ref 引用
components:
  schemas:
    User:
      type: object
      properties:
        id:
          type: integer

paths:
  /users/{id}:
    get:
      responses:
        '200':
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/User'
```

### 3. 使用 Tags 組織端點

使用 tags 將相關的操作分組,提升文檔可讀性。

```yaml
tags:
  - name: posts
    description: 文章管理相關操作
  - name: users
    description: 使用者管理相關操作

paths:
  /posts:
    get:
      tags:
        - posts
      summary: 取得文章列表

  /users:
    get:
      tags:
        - users
      summary: 取得使用者列表
```

### 4. 善用 Components 提高重用性

將常用的 schemas, responses, parameters 定義在 `components` 中。

```yaml
components:
  # 可重用的資料模型
  schemas:
    Error:
      type: object
      properties:
        code:
          type: string
        message:
          type: string

  # 可重用的回應
  responses:
    NotFound:
      description: 找不到資源
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/Error'

  # 可重用的參數
  parameters:
    PageParam:
      name: page
      in: query
      schema:
        type: integer
        minimum: 1
        default: 1

# 在多個端點重用
paths:
  /posts:
    get:
      parameters:
        - $ref: '#/components/parameters/PageParam'
      responses:
        '404':
          $ref: '#/components/responses/NotFound'

  /users:
    get:
      parameters:
        - $ref: '#/components/parameters/PageParam'
      responses:
        '404':
          $ref: '#/components/responses/NotFound'
```

### 5. 明確定義安全性機制

在 `components/securitySchemes` 中定義認證方式。

```yaml
components:
  securitySchemes:
    # JWT Bearer Token
    bearerAuth:
      type: http
      scheme: bearer
      bearerFormat: JWT
      description: |
        使用 JWT Bearer Token 進行認證。
        在 Authorization header 中帶入: `Bearer <token>`

    # API Key
    apiKey:
      type: apiKey
      in: header
      name: X-API-Key
      description: API Key 認證

    # OAuth 2.0
    oauth2:
      type: oauth2
      flows:
        authorizationCode:
          authorizationUrl: https://example.com/oauth/authorize
          tokenUrl: https://example.com/oauth/token
          scopes:
            read: 讀取權限
            write: 寫入權限

# 全域套用 (所有端點預設需要認證)
security:
  - bearerAuth: []

paths:
  # 公開端點 - 覆蓋全域設定
  /public/posts:
    get:
      security: []  # 不需要認證

  # 特定端點需要特定認證
  /admin/users:
    get:
      security:
        - bearerAuth: []
        - apiKey: []  # 任一方式即可
```

### 6. 提供詳細的範例 (Examples)

範例有助於 API 使用者理解預期的資料格式。

```yaml
components:
  schemas:
    Post:
      type: object
      properties:
        title:
          type: string
          example: OpenAPI 教學  # 屬性層級的範例
        content:
          type: string

paths:
  /posts:
    post:
      requestBody:
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/Post'
            example:  # 完整請求範例
              title: 我的文章
              content: 這是內容
      responses:
        '201':
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Post'
              examples:  # 多個範例
                draft:
                  summary: 草稿文章
                  value:
                    id: 1
                    title: 草稿
                    status: draft
                published:
                  summary: 已發布文章
                  value:
                    id: 2
                    title: 已發布
                    status: published
```

### 7. 大型 API 的組織策略

將大型 API 分割成多個檔案,使用 `$ref` 引用外部檔案。

**目錄結構:**
```
openapi/
├── openapi.yaml              # 主檔案
├── paths/
│   ├── posts.yaml            # 文章相關端點
│   ├── users.yaml            # 使用者相關端點
│   └── comments.yaml         # 評論相關端點
├── components/
│   ├── schemas/
│   │   ├── Post.yaml
│   │   ├── User.yaml
│   │   └── Comment.yaml
│   ├── responses/
│   │   └── errors.yaml
│   ├── parameters/
│   │   └── common.yaml
│   └── securitySchemes.yaml
└── README.md
```

**主檔案 (openapi.yaml):**
```yaml
openapi: 3.1.0
info:
  title: My API
  version: 1.0.0

paths:
  /posts:
    $ref: './paths/posts.yaml#/posts'
  /posts/{id}:
    $ref: './paths/posts.yaml#/posts-id'
  /users:
    $ref: './paths/users.yaml#/users'

components:
  schemas:
    Post:
      $ref: './components/schemas/Post.yaml'
    User:
      $ref: './components/schemas/User.yaml'
```

**外部檔案 (paths/posts.yaml):**
```yaml
posts:
  get:
    summary: 取得文章列表
    responses:
      '200':
        description: 成功
        content:
          application/json:
            schema:
              type: array
              items:
                $ref: '../components/schemas/Post.yaml'

posts-id:
  get:
    summary: 取得單一文章
    parameters:
      - name: id
        in: path
        required: true
        schema:
          type: integer
```

### 8. 使用 operationId

為每個操作定義唯一的 `operationId`,方便程式碼生成工具使用。

```yaml
paths:
  /posts:
    get:
      operationId: listPosts  # 唯一識別碼
      summary: 取得文章列表
    post:
      operationId: createPost
      summary: 建立文章

  /posts/{id}:
    get:
      operationId: getPost
      summary: 取得文章
    put:
      operationId: updatePost
      summary: 更新文章
    delete:
      operationId: deletePost
      summary: 刪除文章
```

### 9. 完整的錯誤處理

為每個端點定義可能的錯誤回應。

```yaml
paths:
  /posts/{id}:
    get:
      responses:
        '200':
          description: 成功
        '400':
          description: 錯誤的請求參數
        '401':
          description: 未授權
        '403':
          description: 權限不足
        '404':
          description: 找不到資源
        '429':
          description: 請求次數過多
        '500':
          description: 伺服器錯誤
        '503':
          description: 服務暫時無法使用
```

### 10. 版本管理策略

**方式一:在 URL 中指定版本**
```yaml
servers:
  - url: https://api.example.com/v1
  - url: https://api.example.com/v2
```

**方式二:使用 header 指定版本**
```yaml
paths:
  /posts:
    get:
      parameters:
        - name: API-Version
          in: header
          schema:
            type: string
            enum: ['1.0', '2.0']
```

**方式三:使用不同的檔案**
```
openapi-v1.yaml
openapi-v2.yaml
```

## 工具與資源

### 編輯器

- **Swagger Editor** - 線上編輯器,即時驗證與預覽
  - https://editor.swagger.io/
- **SwaggerHub** - 雲端協作平台,支援團隊協作
- **VS Code 擴充套件**:
  - OpenAPI (Swagger) Editor
  - YAML Language Support
  - Swagger Viewer

### 文檔生成工具

- **Swagger UI** - 最流行的互動式 API 文檔
  - 可直接在瀏覽器中測試 API
- **ReDoc** - 美觀的三欄式文檔介面
  - 適合公開 API 文檔
- **RapiDoc** - 輕量級,可自訂樣式
- **Stoplight Elements** - 現代化的 API 文檔元件

### 驗證與 Linting

- **Spectral** - OpenAPI 規格驗證與 Linting 工具
  ```bash
  npm install -g @stoplight/spectral-cli
  spectral lint openapi.yaml
  ```
- **openapi-validator** - IBM 開發的驗證工具
- **Redocly CLI** - 驗證與建置工具

### Mock Server

- **Prism** - 基於 OpenAPI 的 Mock Server
  ```bash
  npm install -g @stoplight/prism-cli
  prism mock openapi.yaml
  ```
- **Mockoon** - 視覺化 Mock API 工具
- **Postman Mock Server** - Postman 內建 Mock 功能

### 程式碼生成

- **OpenAPI Generator** - 支援 50+ 語言的程式碼生成
  ```bash
  openapi-generator-cli generate \
    -i openapi.yaml \
    -g php \
    -o ./generated
  ```
  支援的生成目標:
  - 客戶端 SDK (PHP, JavaScript, Python, Java 等)
  - 伺服器端骨架 (Symfony, Laravel, Spring 等)
  - API 文檔

- **Swagger Codegen** - 官方程式碼生成工具

### 測試工具

- **Postman** - 支援匯入 OpenAPI 進行 API 測試
- **Insomnia** - REST API 測試工具
- **Dredd** - API 測試框架,驗證實作是否符合規格
- **Schemathesis** - 基於屬性的 API 測試工具

### CI/CD 整合

```yaml
# GitHub Actions 範例
name: OpenAPI Validation

on: [push, pull_request]

jobs:
  validate:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2

      - name: Validate OpenAPI
        uses: char0n/swagger-editor-validate@v1
        with:
          definition-file: openapi.yaml

      - name: Lint with Spectral
        run: |
          npm install -g @stoplight/spectral-cli
          spectral lint openapi.yaml
```

## 撰寫流程建議

### 階段一:規劃

1. **定義 API 目標** - 明確 API 的用途和目標使用者
2. **設計資料模型** - 先設計核心的資料結構
3. **規劃端點結構** - 列出需要的端點和操作
4. **確定認證方式** - 選擇適合的認證機制

### 階段二:撰寫

1. **建立基本資訊**
   ```yaml
   openapi: 3.1.0
   info:
     title: Your API
     version: 1.0.0
   servers:
     - url: https://api.example.com
   ```

2. **定義資料模型** (components/schemas)
   ```yaml
   components:
     schemas:
       User:
         type: object
         properties:
           id:
             type: integer
   ```

3. **定義端點** (paths)
   ```yaml
   paths:
     /users:
       get:
         summary: 取得使用者列表
   ```

4. **設定安全性** (securitySchemes)
   ```yaml
   components:
     securitySchemes:
       bearerAuth:
         type: http
         scheme: bearer
   ```

5. **加入範例和描述**

### 階段三:驗證

1. **語法驗證** - 使用 Swagger Editor 或 Spectral
2. **規範檢查** - 確保符合 OpenAPI 3.1 標準
3. **風格一致性** - 使用 Spectral ruleset
4. **團隊審查** - Pull Request 審查

### 階段四:發布

1. **生成文檔** - 使用 Swagger UI 或 ReDoc
2. **版本控制** - 提交到 Git
3. **CI/CD 整合** - 自動化驗證和部署
4. **通知相關人員** - 通知 API 使用者

## 檔案命名建議

**主檔案:**
- `openapi.yaml` (推薦,YAML 格式較易讀)
- `openapi.json` (適合程式處理)
- `api-spec.yaml`

**避免使用:**
- `swagger.yaml` (舊版命名,容易混淆)

## 常見問題與解決方案

### Q1: 如何處理版本升級?

**A:** 維護多個版本的 OpenAPI 檔案,或使用 server URL 區分版本。

```yaml
servers:
  - url: https://api.example.com/v1
    description: Version 1 (deprecated)
  - url: https://api.example.com/v2
    description: Version 2 (current)
```

### Q2: 如何描述檔案上傳?

**A:** 使用 `multipart/form-data` 和 `binary` 格式。

```yaml
paths:
  /upload:
    post:
      requestBody:
        content:
          multipart/form-data:
            schema:
              type: object
              properties:
                file:
                  type: string
                  format: binary
                description:
                  type: string
```

### Q3: 如何處理動態欄位?

**A:** 使用 `additionalProperties`。

```yaml
components:
  schemas:
    DynamicObject:
      type: object
      properties:
        id:
          type: integer
      additionalProperties:
        type: string  # 其他欄位都是字串
```

### Q4: 如何描述多型 (Polymorphism)?

**A:** 使用 `oneOf`, `anyOf`, 或 `allOf`。

```yaml
components:
  schemas:
    Pet:
      type: object
      discriminator:
        propertyName: petType
      required:
        - petType
      properties:
        petType:
          type: string
      oneOf:
        - $ref: '#/components/schemas/Cat'
        - $ref: '#/components/schemas/Dog'

    Cat:
      allOf:
        - $ref: '#/components/schemas/Pet'
        - type: object
          properties:
            meow:
              type: string

    Dog:
      allOf:
        - $ref: '#/components/schemas/Pet'
        - type: object
          properties:
            bark:
              type: string
```

## OpenAPI 與 Symfony 整合

### 使用 NelmioApiDocBundle

**安裝:**
```bash
composer require nelmio/api-doc-bundle
```

**設定 (config/packages/nelmio_api_doc.yaml):**
```yaml
nelmio_api_doc:
    documentation:
        info:
            title: My API
            description: API Description
            version: 1.0.0
        paths:
            /api/posts:
                get:
                    summary: Get posts
                    responses:
                        '200':
                            description: Success
    areas:
        path_patterns:
            - ^/api(?!/doc$)
```

**在 Controller 中使用 Attributes:**
```php
use OpenApi\Attributes as OA;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/posts', methods: ['GET'])]
#[OA\Get(
    path: '/api/posts',
    summary: '取得文章列表',
    tags: ['Posts']
)]
#[OA\Parameter(
    name: 'page',
    in: 'query',
    schema: new OA\Schema(type: 'integer', minimum: 1)
)]
#[OA\Response(
    response: 200,
    description: '成功',
    content: new OA\JsonContent(
        type: 'array',
        items: new OA\Items(ref: '#/components/schemas/Post')
    )
)]
public function list(): Response
{
    // ...
}
```

## 參考資源

### 官方文件
- [OpenAPI 3.1 Specification](https://spec.openapis.org/oas/v3.1.0)
- [OpenAPI Guide](https://learn.openapis.org/)
- [Swagger 官方網站](https://swagger.io/)

### 中文資源
- [OpenAPI 打通前後端任督二脈](https://editor.leonh.space/2022/openapi/)
- [Microsoft Learn - OpenAPI 規格](https://learn.microsoft.com/zh-tw/microsoft-cloud/dev/dev-proxy/concepts/what-is-openapi-spec)

### 工具網站
- [Swagger Editor](https://editor.swagger.io/) - 線上編輯器
- [OpenAPI Generator](https://openapi-generator.tech/) - 程式碼生成
- [Spectral](https://stoplight.io/open-source/spectral) - Linting 工具
- [Prism](https://stoplight.io/open-source/prism) - Mock Server

### 最佳實踐
- [OpenAPI Best Practices](https://learn.openapis.org/best-practices.html)
- [API Design Guidelines](https://opensource.zalando.com/restful-api-guidelines/)

### 社群與支援
- [OpenAPI GitHub Repository](https://github.com/OAI/OpenAPI-Specification)
- [Swagger Community](https://community.smartbear.com/)
- [Stack Overflow - OpenAPI Tag](https://stackoverflow.com/questions/tagged/openapi)
