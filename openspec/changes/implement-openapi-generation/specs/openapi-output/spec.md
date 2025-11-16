# Capability: OpenAPI Output

OpenAPI 文檔輸出與組裝能力

## ADDED Requirements

### Requirement: OpenAPI Document Structure

系統 SHALL 能夠生成符合 OpenAPI 3.1 規範的完整 JSON 文檔結構。

#### Scenario: 生成基本 OpenAPI 文檔結構

- **GIVEN** 系統完成路由與 Schema 分析
- **WHEN** OpenApiGenerator 執行生成
- **THEN** 輸出包含以下必要欄位：
  - `openapi: "3.1.0"`
  - `info: {title, version, description}`
  - `paths: {}`
  - `components: {schemas: {}}`

#### Scenario: 從設定填入 Info 區塊

- **GIVEN** 設定檔包含：
  ```yaml
  info:
    title: 'My API'
    version: '2.0.0'
    description: 'API Description'
  ```
- **WHEN** 系統生成 OpenAPI 文檔
- **THEN** `info` 區塊包含設定中的值
- **AND** 若設定缺少某欄位則使用預設值

### Requirement: Paths Generation

系統 SHALL 能夠從分析的路由資訊生成 OpenAPI `paths` 區塊。

#### Scenario: 生成 Path Item

- **GIVEN** 路由定義為 `GET /api/posts`
- **WHEN** RouteDescriber 處理該路由
- **THEN** 在 `paths` 中建立 `/api/posts` 項目
- **AND** 包含 `get` operation 定義

#### Scenario: 同一路徑多個 HTTP 方法

- **GIVEN** 兩個路由：`GET /api/posts/{id}` 和 `PUT /api/posts/{id}`
- **WHEN** 系統生成 paths
- **THEN** `/api/posts/{id}` 路徑項目包含 `get` 和 `put` 兩個 operations
- **AND** 兩個 operations 共享相同的 path parameters

### Requirement: Operation Generation

系統 SHALL 能夠為每個 HTTP 方法生成完整的 Operation 定義，包含 parameters、requestBody、responses。

#### Scenario: 生成 Operation 基本資訊

- **GIVEN** Controller 方法標註 `#[Route('/api/posts', name: 'api_posts_list', methods: ['GET'])]`
- **WHEN** OperationDescriber 處理該方法
- **THEN** operation 包含：
  - `operationId: "api_posts_list"`
  - `summary: "List posts"` (從方法名稱推導或使用預設)
  - `tags: ["posts"]` (從路徑推導)

#### Scenario: 生成 Parameters

- **GIVEN** 方法包含 `#[MapQueryParameter] int $page` 和路徑參數 `{id}`
- **WHEN** 系統生成該 operation
- **THEN** `parameters` 陣列包含：
  - Path parameter: `{name: "id", in: "path", required: true, schema: {type: "integer"}}`
  - Query parameter: `{name: "page", in: "query", required: false, schema: {type: "integer"}}`

#### Scenario: 生成 Request Body

- **GIVEN** 方法參數為 `#[MapRequestPayload] PostDto $dto`
- **WHEN** 系統生成 requestBody
- **THEN** operation 包含：
  ```json
  {
    "requestBody": {
      "required": true,
      "content": {
        "application/json": {
          "schema": {
            "$ref": "#/components/schemas/PostDto"
          }
        }
      }
    }
  }
  ```

#### Scenario: 生成 Responses

- **GIVEN** 方法回傳型別為 `PostDto`
- **WHEN** 系統生成 responses
- **THEN** operation 包含：
  ```json
  {
    "responses": {
      "200": {
        "description": "Successful response",
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/PostDto"
            }
          }
        }
      }
    }
  }
  ```

### Requirement: Components Schemas Generation

系統 SHALL 能夠將 SchemaRegistry 中的所有 Schema 整合到 OpenAPI 的 `components.schemas` 區塊。

#### Scenario: 整合所有註冊的 Schemas

- **GIVEN** SchemaRegistry 包含 `PostDto`、`AuthorDto`、`CommentDto` 三個 Schema
- **WHEN** 系統組裝 OpenAPI 文檔
- **THEN** `components.schemas` 包含這三個 Schema 的完整定義
- **AND** 每個 Schema 使用短類別名稱作為 key

#### Scenario: 處理 Schema 名稱衝突

- **GIVEN** 專案有兩個同名類別：`App\DTO\PostDto` 和 `App\Admin\DTO\PostDto`
- **WHEN** 兩個都需要註冊到 Registry
- **THEN** 第二個使用命名空間前綴：`AdminPostDto`
- **AND** 所有參照正確使用對應名稱

### Requirement: Servers Configuration

系統 SHALL 能夠從設定檔讀取並生成 OpenAPI `servers` 區塊。

#### Scenario: 從設定載入 Servers

- **GIVEN** 設定檔包含：
  ```yaml
  servers:
    - url: 'https://api.example.com/v1'
      description: 'Production'
    - url: 'https://staging.example.com/v1'
      description: 'Staging'
  ```
- **WHEN** 系統生成 OpenAPI 文檔
- **THEN** `servers` 陣列包含兩個 server 定義
- **AND** 保留原始的 url 和 description

### Requirement: Caching Strategy

系統 SHALL 實作多層快取機制以優化重複請求的效能。

#### Scenario: L1 Request 快取

- **GIVEN** 同一次 HTTP 請求中多次呼叫 `OpenApiGenerator::generate()`
- **WHEN** 第二次呼叫發生
- **THEN** 系統從 instance property 回傳快取結果
- **AND** 不重新執行路由分析

#### Scenario: L2 Symfony Cache 快取

- **GIVEN** 第一次請求完成生成並快取
- **AND** TTL 設定為 3600 秒
- **WHEN** 第二次請求（不同 HTTP 請求）呼叫生成
- **THEN** 系統從 Symfony Cache 讀取結果
- **AND** 不重新執行分析流程
- **AND** 回應時間 < 50ms

#### Scenario: 快取失效

- **GIVEN** 快取的 OpenAPI 文檔存在
- **AND** 快取 TTL 已過期
- **WHEN** 新請求到達
- **THEN** 系統重新執行完整分析
- **AND** 更新快取內容

### Requirement: Environment-Aware Cache TTL

系統 SHALL 根據執行環境自動調整快取 TTL。

#### Scenario: 開發環境短 TTL

- **GIVEN** 環境為 `dev`
- **AND** 未明確設定 TTL
- **WHEN** 系統初始化快取
- **THEN** TTL 設定為 60 秒
- **AND** 開發者可在 1 分鐘內看到變更

#### Scenario: 生產環境長 TTL

- **GIVEN** 環境為 `prod`
- **AND** 未明確設定 TTL
- **WHEN** 系統初始化快取
- **THEN** TTL 設定為 86400 秒（24 小時）
- **AND** 減少生產環境的分析開銷

### Requirement: Tag Generation

系統 SHALL 能夠自動生成或從 Attributes 提取 Operation tags。

#### Scenario: 從路徑推導 Tag

- **GIVEN** 路由路徑為 `/api/posts/{id}`
- **AND** Controller 未明確指定 tag
- **WHEN** 系統生成該 operation
- **THEN** 自動設定 `tags: ["posts"]`
- **AND** tag 名稱從路徑第一個區段提取

#### Scenario: 從 Controller 類別推導 Tag

- **GIVEN** Controller 類別名稱為 `BlogPostController`
- **WHEN** 系統生成 tags
- **THEN** 使用 `blog-post` 作為 tag 名稱

### Requirement: Operation ID Generation

系統 SHALL 能夠為每個 operation 生成唯一且有意義的 operationId。

#### Scenario: 使用 Route Name 作為 Operation ID

- **GIVEN** 路由定義 `#[Route('/api/posts', name: 'api_posts_list')]`
- **WHEN** 系統生成 operation
- **THEN** `operationId` 設定為 `api_posts_list`

#### Scenario: 自動生成 Operation ID

- **GIVEN** 路由未定義 `name`
- **AND** Controller 方法為 `BlogController::list()`
- **WHEN** 系統生成 operationId
- **THEN** 使用格式 `{controllerName}_{methodName}` → `blog_list`

### Requirement: JSON Serialization

系統 SHALL 能夠將組裝的 OpenAPI 陣列序列化為格式化的 JSON。

#### Scenario: 序列化為 JSON

- **GIVEN** OpenAPI 文檔組裝完成（PHP 陣列）
- **WHEN** 呼叫生成方法的 JSON 輸出
- **THEN** 回傳格式化的 JSON 字串
- **AND** 使用 `JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE` 選項
- **AND** 輸出可讀性佳

### Requirement: Internal Route Filtering

系統 SHALL 能夠過濾內部與除錯用途的路由，僅輸出應用程式 API 路由。

#### Scenario: 排除 Symfony Profiler 路由

- **GIVEN** 專案包含 `_profiler`、`_wdt`、`_error` 等內部路由
- **AND** 設定 `include_internal_routes` 為 `false`
- **WHEN** 系統生成 paths
- **THEN** 所有名稱以底線開頭的路由被排除
- **AND** 僅包含應用程式定義的路由

#### Scenario: 包含內部路由

- **GIVEN** 設定 `include_internal_routes` 為 `true`
- **WHEN** 系統生成文檔
- **THEN** 包含所有路由（包含內部路由）

### Requirement: Error Handling in Generation

系統 SHALL 優雅處理生成過程中的錯誤，提供部分文檔而非完全失敗。

#### Scenario: 單一路由分析失敗

- **GIVEN** 100 個路由中有 1 個分析時拋出異常
- **WHEN** OpenApiGenerator 執行生成
- **THEN** 捕捉該異常並記錄錯誤
- **AND** 繼續處理其他 99 個路由
- **AND** 最終文檔包含 99 個成功的 operations

#### Scenario: Schema 分析失敗的降級處理

- **GIVEN** 某個 DTO 分析失敗（例如類別不存在）
- **WHEN** 系統遇到該 DTO
- **THEN** 記錄 Warning 並使用降級 Schema：`{type: "object", additionalProperties: true}`
- **AND** 文檔生成繼續進行

### Requirement: Validation Against OpenAPI Spec

系統 SHALL 確保生成的文檔符合 OpenAPI 3.1 規範的結構要求。

#### Scenario: 驗證必要欄位存在

- **GIVEN** OpenAPI 文檔生成完成
- **WHEN** 系統執行自我驗證（開發/測試環境）
- **THEN** 確認包含所有必要欄位：`openapi`、`info`、`paths`
- **AND** `info` 包含 `title` 和 `version`

#### Scenario: 驗證 Schema 參照有效性

- **GIVEN** 文檔中使用了 `$ref: "#/components/schemas/PostDto"`
- **WHEN** 系統驗證參照
- **THEN** 確認 `components.schemas.PostDto` 確實存在
- **AND** 若不存在則記錄錯誤
