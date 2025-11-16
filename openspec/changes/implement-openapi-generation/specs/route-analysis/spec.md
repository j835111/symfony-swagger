# Capability: Route Analysis

路由與 Attributes 分析能力

## ADDED Requirements

### Requirement: Route Collection Extraction

系統 SHALL 能夠從 Symfony Router 服務擷取所有已註冊的路由資訊。

#### Scenario: 擷取所有路由

- **GIVEN** Symfony 應用程式包含多個已註冊的路由
- **WHEN** 呼叫 RouteDescriber 的 describe 方法
- **THEN** 系統回傳包含所有路由的集合
- **AND** 每個路由包含 path、methods、name、defaults、requirements 資訊

#### Scenario: 過濾內部路由

- **GIVEN** RouteCollection 包含內部路由（如 `_profiler`、`_wdt`）
- **AND** 設定 `include_internal_routes` 為 `false`
- **WHEN** 擷取路由資訊
- **THEN** 系統排除所有名稱以底線開頭的路由
- **AND** 僅回傳應用程式定義的 API 路由

### Requirement: Controller Reflection Analysis

系統 SHALL 能夠解析路由對應的 Controller 類別與方法，並使用 Reflection API 分析其結構。

#### Scenario: 解析 Controller Callable

- **GIVEN** 一個路由定義為 `App\Controller\BlogController::list`
- **WHEN** 系統分析該路由
- **THEN** 系統成功建立 `ReflectionClass` 和 `ReflectionMethod` 實例
- **AND** 可以存取方法的 Attributes、參數、回傳型別資訊

#### Scenario: 處理無效的 Controller

- **GIVEN** 路由的 Controller 類別不存在或無法載入
- **WHEN** 系統嘗試分析該路由
- **THEN** 系統記錄 Warning 日誌
- **AND** 跳過該路由並繼續處理其他路由

### Requirement: Route Attribute Reading

系統 SHALL 能夠讀取並解析 Controller 方法上的 `#[Route]` Attribute 及其所有參數。

#### Scenario: 讀取基本 Route Attribute

- **GIVEN** Controller 方法標註 `#[Route('/api/posts', name: 'api_posts', methods: ['GET'])]`
- **WHEN** AttributeReader 讀取該方法的 Attributes
- **THEN** 系統正確提取 path 為 `/api/posts`
- **AND** 提取 name 為 `api_posts`
- **AND** 提取 methods 為 `['GET']`

#### Scenario: 讀取 Route 參數與限制

- **GIVEN** Route Attribute 包含 `requirements: ['id' => '\d+']` 和 `defaults: ['page' => 1]`
- **WHEN** 系統分析 Route Attribute
- **THEN** 系統提取 requirements 並對應到 OpenAPI path parameter pattern
- **AND** 提取 defaults 並設定為 parameter 的預設值

### Requirement: Request Mapping Attributes Analysis

系統 SHALL 能夠辨識並解析請求對應相關的 Attributes（`#[MapRequestPayload]`、`#[MapQueryParameter]`、`#[MapQueryString]`、`#[MapUploadedFile]`）。

#### Scenario: 偵測 MapRequestPayload Attribute

- **GIVEN** Controller 方法參數標註 `#[MapRequestPayload] PostDto $dto`
- **WHEN** OperationDescriber 分析方法參數
- **THEN** 系統識別該參數為 Request Body
- **AND** 觸發 SchemaDescriber 分析 `PostDto` 類別
- **AND** 生成對應的 `requestBody` OpenAPI 定義

#### Scenario: 偵測 MapQueryParameter Attribute

- **GIVEN** Controller 方法參數標註 `#[MapQueryParameter] string $search`
- **WHEN** 系統分析該方法
- **THEN** 系統將該參數新增到 `parameters` 陣列
- **AND** 設定 `in: query` 和 `name: search`
- **AND** 從型別推導 `schema.type: string`

#### Scenario: 偵測 MapQueryString Attribute

- **GIVEN** Controller 方法參數標註 `#[MapQueryString] SearchDto $query`
- **WHEN** 系統分析該方法
- **THEN** 系統分析 `SearchDto` 的所有屬性
- **AND** 將每個屬性作為獨立的 query parameter
- **AND** 保留屬性的型別與驗證規則

#### Scenario: 偵測 MapUploadedFile Attribute

- **GIVEN** Controller 方法參數標註 `#[MapUploadedFile] UploadedFile $file`
- **WHEN** 系統分析該方法
- **THEN** 系統生成 `requestBody` 定義
- **AND** 設定 `content-type` 為 `multipart/form-data`
- **AND** Schema 包含 `type: string, format: binary` 屬性

### Requirement: Method Parameter Type Extraction

系統 SHALL 能夠擷取 Controller 方法參數的型別資訊，包括名稱、型別、是否可選、預設值。

#### Scenario: 擷取基本型別參數

- **GIVEN** Controller 方法簽名為 `public function show(int $id, string $format = 'json')`
- **WHEN** 系統分析方法參數
- **THEN** 第一個參數識別為必填 integer
- **AND** 第二個參數識別為可選 string，預設值為 'json'

#### Scenario: 擷取類別型別參數

- **GIVEN** 方法參數型別為 `PostDto $dto`
- **WHEN** 系統分析該參數
- **THEN** 系統識別為類別型別
- **AND** 記錄完整類別名稱 `App\DTO\PostDto`
- **AND** 準備進行 Schema 分析

### Requirement: Return Type Analysis

系統 SHALL 能夠分析 Controller 方法的回傳型別，用於推導 Response Schema。

#### Scenario: 分析簡單回傳型別

- **GIVEN** Controller 方法宣告 `public function list(): Response`
- **WHEN** 系統分析回傳型別
- **THEN** 系統識別為 Symfony Response 類別
- **AND** 預設生成 200 回應但不含特定 Schema

#### Scenario: 分析 DTO 回傳型別

- **GIVEN** 方法宣告 `public function show(): PostDto`
- **WHEN** 系統分析回傳型別
- **THEN** 系統觸發 SchemaDescriber 分析 `PostDto`
- **AND** 在 responses.200.content.application/json.schema 中參照該 Schema

#### Scenario: 分析 Union Type 回傳

- **GIVEN** 方法宣告 `public function find(): PostDto|null`
- **WHEN** 系統分析回傳型別
- **THEN** 系統識別為 Union Type
- **AND** 生成包含 200（PostDto）和 404（null）的多重回應定義

### Requirement: Attribute Processing Error Handling

系統 SHALL 優雅處理 Attribute 讀取過程中的錯誤，確保單一錯誤不影響整體文檔生成。

#### Scenario: 處理不存在的 Attribute 類別

- **GIVEN** Controller 使用了專案中未安裝的 Attribute（如 `#[IsGranted]` 但未安裝 security-bundle）
- **WHEN** 系統嘗試讀取該 Attribute
- **THEN** 系統捕捉 ReflectionException
- **AND** 記錄 Warning 日誌說明缺少的 Attribute
- **AND** 繼續處理其他 Attributes 不中斷流程

#### Scenario: 處理 Attribute 參數錯誤

- **GIVEN** Attribute 實例化時拋出異常（參數不符）
- **WHEN** 系統呼叫 `getAttribute()->newInstance()`
- **THEN** 系統捕捉異常並記錄錯誤
- **AND** 該 Attribute 被忽略但不影響其他資訊擷取
