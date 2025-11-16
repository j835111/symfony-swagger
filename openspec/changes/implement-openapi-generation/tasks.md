# Tasks: OpenAPI 自動生成功能實作

## Phase 1: 核心架構與工具 (3-5 天)

### 1.1 建立基礎架構
- [ ] 建立 `src/Analyzer/` 目錄結構
- [ ] 建立 `src/Service/Describer/` 目錄結構
- [ ] 建立 `src/Service/Registry/` 目錄結構
- [ ] 更新 Composer autoload 設定

### 1.2 實作 AttributeReader
- [ ] 建立 `AttributeReader` 類別 (`src/Analyzer/AttributeReader.php`)
- [ ] 實作 `readRouteAttribute()` - 讀取 #[Route] Attribute
- [ ] 實作 `readRequestAttributes()` - 讀取 #[MapRequestPayload] 等
- [ ] 實作 `getParametersFromAttributes()` - 從 Attributes 提取參數資訊
- [ ] 撰寫 `AttributeReaderTest` 單元測試
- [ ] 測試覆蓋率 > 80%

### 1.3 實作 TypeAnalyzer
- [ ] 建立 `TypeAnalyzer` 類別 (`src/Analyzer/TypeAnalyzer.php`)
- [ ] 實作 `analyze()` 主方法 - 型別分析入口
- [ ] 實作 `analyzeBuiltinType()` - 處理 string, int, float, bool, array
- [ ] 實作 `analyzeUnionType()` - 處理 Union Types (PHP 8.0+)
- [ ] 實作 `analyzeClassType()` - 處理類別型別
- [ ] 實作 `extractFromDocBlock()` - 從 DocBlock 推導陣列元素型別
- [ ] 實作特殊類別對應 (DateTime, BackedEnum 等)
- [ ] 實作最大深度保護機制
- [ ] 撰寫 `TypeAnalyzerTest` 單元測試
- [ ] 測試所有型別對應情境

### 1.4 實作 SchemaRegistry
- [ ] 建立 `SchemaRegistry` 類別 (`src/Service/Registry/SchemaRegistry.php`)
- [ ] 實作 `register()` - 註冊 Schema
- [ ] 實作 `has()` - 檢查 Schema 是否已註冊
- [ ] 實作 `getReference()` - 取得 $ref 路徑
- [ ] 實作 `getSchemas()` - 取得所有註冊的 Schemas
- [ ] 實作循環引用偵測機制 (`isAnalyzing()`, `markAnalyzing()`)
- [ ] 實作 Schema 名稱生成與衝突處理
- [ ] 撰寫 `SchemaRegistryTest` 單元測試

## Phase 2: Describer 實作 (5-7 天)

### 2.1 實作 RouteDescriber
- [ ] 建立 `RouteDescriber` 類別 (`src/Service/Describer/RouteDescriber.php`)
- [ ] 實作 `describe()` - 從 Router 擷取路由
- [ ] 實作 `shouldIncludeRoute()` - 路由過濾邏輯（排除內部路由）
- [ ] 實作 `extractControllerCallable()` - 解析 Controller callable
- [ ] 實作 `getReflectionMethod()` - 取得 ReflectionMethod
- [ ] 處理無效 Controller 的錯誤情況
- [ ] 撰寫 `RouteDescriberTest` 單元測試

### 2.2 實作 OperationDescriber
- [ ] 建立 `OperationDescriber` 類別 (`src/Service/Describer/OperationDescriber.php`)
- [ ] 實作 `describe()` - 生成 Operation 定義
- [ ] 實作 `describeParameters()` - 生成 parameters 陣列
  - [ ] 處理 Path parameters (從 Route requirements)
  - [ ] 處理 Query parameters (#[MapQueryParameter])
  - [ ] 處理 Query DTO (#[MapQueryString])
- [ ] 實作 `describeRequestBody()` - 生成 requestBody
  - [ ] 偵測 #[MapRequestPayload]
  - [ ] 處理 #[MapUploadedFile]（multipart/form-data）
- [ ] 實作 `describeResponses()` - 生成 responses
  - [ ] 從回傳型別推導 200 response
  - [ ] 處理 Union Type 回傳（多重狀態碼）
- [ ] 實作 `generateOperationId()` - 生成 operationId
- [ ] 實作 `generateTags()` - 生成 tags
- [ ] 撰寫 `OperationDescriberTest` 單元測試

### 2.3 實作 SchemaDescriber
- [ ] 建立 `SchemaDescriber` 類別 (`src/Service/Describer/SchemaDescriber.php`)
- [ ] 實作 `describe()` - 分析 DTO 類別生成 Schema
- [ ] 實作 `describeProperties()` - 分析所有 public 屬性
- [ ] 實作 `extractConstraints()` - 讀取 Symfony Validator Constraints
- [ ] 實作 ConstraintToSchemaConverter
  - [ ] 轉換 Length → minLength, maxLength
  - [ ] 轉換 Range → minimum, maximum
  - [ ] 轉換 Email → format: email
  - [ ] 轉換 Choice → enum
  - [ ] 轉換 NotBlank → minLength: 1
  - [ ] 轉換 Count → minItems, maxItems
- [ ] 實作 `shouldIncludeProperty()` - 處理 #[Groups] 過濾
- [ ] 實作遞迴分析邏輯
- [ ] 實作循環引用檢測與處理
- [ ] 撰寫 `SchemaDescriberTest` 單元測試
- [ ] 測試深層巢狀與循環引用情境

## Phase 3: OpenApiGenerator 主服務 (3-5 天)

### 3.1 重構 SwaggerGenerator
- [ ] 更新 `SwaggerGenerator` 類別（向後相容）
- [ ] 委派給新的 `OpenApiGenerator` 服務
- [ ] 保留現有公開介面

### 3.2 實作 OpenApiGenerator
- [ ] 建立 `OpenApiGenerator` 類別 (`src/Service/OpenApiGenerator.php`)
- [ ] 注入依賴：Router, Cache, AttributeReader, TypeAnalyzer, SchemaRegistry
- [ ] 實作 `generate()` - 主生成方法
- [ ] 實作快取邏輯
  - [ ] L1 Request 快取（instance property）
  - [ ] L2 Symfony Cache 整合
  - [ ] 環境感知 TTL 設定
- [ ] 實作 `generatePaths()` - 組裝 paths 區塊
- [ ] 實作 `generateSchemas()` - 組裝 components.schemas
- [ ] 實作 `generateInfo()` - 從設定載入 info
- [ ] 實作 `generateServers()` - 從設定載入 servers
- [ ] 實作錯誤處理與優雅降級
- [ ] 實作 JSON 序列化
- [ ] 撰寫 `OpenApiGeneratorTest` 整合測試

### 3.3 設定管理
- [ ] 更新 `DependencyInjection/Configuration.php`
  - [ ] 新增 `generation_mode` 選項
  - [ ] 新增 `cache.enabled` 和 `cache.ttl` 選項
  - [ ] 新增 `analysis.max_depth` 選項
  - [ ] 新增 `analysis.include_internal_routes` 選項
  - [ ] 新增 `info` 區塊設定
  - [ ] 新增 `servers` 陣列設定
- [ ] 更新 `DependencyInjection/SymfonySwaggerExtension.php`
  - [ ] 載入設定並傳遞給服務
  - [ ] 註冊所有新增的服務
- [ ] 建立預設設定檔範本 (`config/packages/symfony_swagger.yaml`)

### 3.4 服務註冊
- [ ] 更新 `config/services.php`
- [ ] 註冊 `AttributeReader` 服務
- [ ] 註冊 `TypeAnalyzer` 服務（注入 max_depth 設定）
- [ ] 註冊 `SchemaRegistry` 服務
- [ ] 註冊 `RouteDescriber` 服務
- [ ] 註冊 `OperationDescriber` 服務
- [ ] 註冊 `SchemaDescriber` 服務
- [ ] 註冊 `OpenApiGenerator` 主服務
- [ ] 設定服務為 public（供 Controller 使用）

## Phase 4: 測試與品質保證 (2-3 天)

### 4.1 單元測試完善
- [ ] 確保所有類別都有對應測試
- [ ] 測試覆蓋率達到 > 80%
- [ ] 撰寫邊界情況測試
  - [ ] 空路由集合
  - [ ] 無型別提示的參數
  - [ ] 深層巢狀 DTO（超過 max_depth）
  - [ ] 循環引用 DTO
  - [ ] Union Types 與 Nullable Types
  - [ ] BackedEnum 型別

### 4.2 整合測試
- [ ] 建立測試用 Controller (`tests/Fixtures/TestController.php`)
  - [ ] 包含各種 Attributes 組合
  - [ ] 包含不同型別的參數與回傳值
- [ ] 建立測試用 DTO (`tests/Fixtures/`)
  - [ ] 簡單 DTO
  - [ ] 巢狀 DTO
  - [ ] 包含 Validator Constraints 的 DTO
  - [ ] 循環引用 DTO
- [ ] 撰寫完整流程整合測試
  - [ ] 測試完整生成流程
  - [ ] 驗證生成的 JSON 結構
  - [ ] 驗證 Schema 參照正確性
- [ ] 測試快取機制
  - [ ] L1 快取命中測試
  - [ ] L2 快取命中測試
  - [ ] 快取失效測試

### 4.3 OpenAPI 規範驗證
- [ ] 使用 OpenAPI Validator 工具驗證生成的文檔
- [ ] 確保符合 OpenAPI 3.1 規範
- [ ] 修正所有驗證錯誤

### 4.4 程式碼品質
- [ ] 執行 PHPStan Level 8 分析
- [ ] 修正所有 PHPStan 錯誤與警告
- [ ] 執行 PHP-CS-Fixer
- [ ] 確保符合 PSR-12 編碼標準
- [ ] 新增所有必要的 PHPDoc 註解
- [ ] 檢查型別提示完整性

## Phase 5: 文檔與範例 (1-2 天)

### 5.1 使用文檔
- [ ] 撰寫 `README.md` 更新
  - [ ] 安裝說明
  - [ ] 基本設定
  - [ ] 使用範例
- [ ] 撰寫 `docs/usage.md`
  - [ ] 詳細設定選項說明
  - [ ] 支援的 Attributes 清單
  - [ ] 型別對應表
  - [ ] Validator Constraints 轉換表
- [ ] 撰寫 `docs/examples.md`
  - [ ] 基本 CRUD API 範例
  - [ ] 檔案上傳範例
  - [ ] 複雜 DTO 範例

### 5.2 範例專案
- [ ] 建立 `examples/` 目錄
- [ ] 建立範例 Controller
- [ ] 建立範例 DTO
- [ ] 提供設定檔範例
- [ ] 提供生成的 OpenAPI JSON 範例

## Phase 6: 最終驗證與調整 (1 天)

### 6.1 效能測試
- [ ] Benchmark 小型專案（< 50 端點）
- [ ] Benchmark 中型專案（50-200 端點）
- [ ] 驗證快取效能提升
- [ ] 確認符合預期效能指標

### 6.2 最終檢查
- [ ] 執行完整測試套件
- [ ] 驗證所有驗收標準達成
- [ ] 檢查 CHANGELOG.md 更新
- [ ] 更新 composer.json 版本號
- [ ] 執行 `openspec validate implement-openapi-generation --strict`
- [ ] 修正所有 OpenSpec 驗證錯誤

### 6.3 準備發布
- [ ] 清理除錯程式碼
- [ ] 移除 `dump()` / `dd()` / `var_dump()`
- [ ] 檢查沒有遺留的 TODO 註解
- [ ] 確認所有測試通過
- [ ] 建立 Git tag
- [ ] 準備 Release Notes

## 驗收檢查清單

### 功能性需求
- [ ] 支援所有 Priority 1 Symfony Attributes（5 個）
  - [ ] #[Route]
  - [ ] #[MapRequestPayload]
  - [ ] #[MapQueryParameter]
  - [ ] #[MapQueryString]
  - [ ] #[MapUploadedFile]
- [ ] 支援基本 PHP 型別對應（string, int, bool, float, array）
- [ ] 支援 DTO 類別遞迴分析（最大深度可設定）
- [ ] 支援 Union Types、Nullable Types、Enum
- [ ] 從 Symfony Validator Constraints 轉換驗證規則（至少 6 種）
- [ ] 實作多層快取機制（L1 + L2）
- [ ] 生成符合 OpenAPI 3.1 規範的 JSON
- [ ] 循環引用偵測與處理
- [ ] 優雅錯誤處理（單一錯誤不影響整體）

### 品質需求
- [ ] 測試覆蓋率 > 80%
- [ ] 通過 PHPStan Level 8
- [ ] 通過 PHP-CS-Fixer 檢查（PSR-12）
- [ ] 所有公開方法都有 PHPDoc
- [ ] 無 PHPStan 錯誤或警告

### 文檔需求
- [ ] README.md 包含安裝與基本使用說明
- [ ] docs/ 包含詳細使用文檔
- [ ] 提供範例程式碼
- [ ] 所有設定選項都有說明

### 效能需求
- [ ] 小型專案首次生成 < 100ms
- [ ] 小型專案快取命中 < 5ms
- [ ] 中型專案首次生成 < 500ms
- [ ] 中型專案快取命中 < 10ms

### OpenSpec 需求
- [ ] 通過 `openspec validate --strict`
- [ ] 所有 spec deltas 都有對應實作
- [ ] tasks.md 中所有項目標記為完成

## 預期產出

### 新增檔案

#### 分析器 (Analyzer)
- `src/Analyzer/AttributeReader.php`
- `src/Analyzer/TypeAnalyzer.php`
- `tests/Analyzer/AttributeReaderTest.php`
- `tests/Analyzer/TypeAnalyzerTest.php`

#### 描述器 (Describer)
- `src/Service/Describer/RouteDescriber.php`
- `src/Service/Describer/OperationDescriber.php`
- `src/Service/Describer/SchemaDescriber.php`
- `tests/Service/Describer/RouteDescriberTest.php`
- `tests/Service/Describer/OperationDescriberTest.php`
- `tests/Service/Describer/SchemaDescriberTest.php`

#### 註冊器 (Registry)
- `src/Service/Registry/SchemaRegistry.php`
- `tests/Service/Registry/SchemaRegistryTest.php`

#### 主服務
- `src/Service/OpenApiGenerator.php`
- `tests/Service/OpenApiGeneratorTest.php`

#### 測試 Fixtures
- `tests/Fixtures/TestController.php`
- `tests/Fixtures/DTO/PostDto.php`
- `tests/Fixtures/DTO/AuthorDto.php`
- `tests/Fixtures/DTO/CommentDto.php`
- `tests/Fixtures/DTO/SearchDto.php`

#### 設定
- `config/packages/symfony_swagger.yaml`

#### 文檔
- `docs/usage.md`
- `docs/examples.md`
- `docs/type-mapping.md`
- `docs/constraints.md`

#### 範例
- `examples/BasicController.php`
- `examples/DTO/`
- `examples/generated-openapi.json`

### 修改檔案
- `src/Service/SwaggerGenerator.php` - 重構為委派給 OpenApiGenerator
- `src/DependencyInjection/Configuration.php` - 擴充設定選項
- `src/DependencyInjection/SymfonySwaggerExtension.php` - 註冊新服務
- `config/services.php` - 註冊所有服務
- `composer.json` - 更新版本號
- `README.md` - 更新說明文檔
- `CHANGELOG.md` - 記錄變更

## 里程碑 (Milestones)

### M1: 核心工具完成 (第 3-5 天)
- ✅ AttributeReader 實作完成
- ✅ TypeAnalyzer 實作完成
- ✅ SchemaRegistry 實作完成
- ✅ 單元測試覆蓋率 > 80%

### M2: Describer 層完成 (第 8-12 天)
- ✅ RouteDescriber 實作完成
- ✅ OperationDescriber 實作完成
- ✅ SchemaDescriber 實作完成
- ✅ 整合測試通過

### M3: 主服務完成 (第 13-17 天)
- ✅ OpenApiGenerator 實作完成
- ✅ 快取機制運作正常
- ✅ 設定管理完成
- ✅ 服務註冊完成

### M4: 品質達標 (第 18-19 天)
- ✅ 測試覆蓋率 > 80%
- ✅ PHPStan Level 8 通過
- ✅ OpenAPI 規範驗證通過
- ✅ 效能指標達成

### M5: 文檔與發布 (第 20-21 天)
- ✅ 使用文檔完成
- ✅ 範例專案完成
- ✅ 所有驗收標準達成
- ✅ 準備發布

## 風險管理

### 高風險項目
- DTO 循環引用處理 → 提早測試與驗證
- 效能不符預期 → 及早 Benchmark，必要時調整快取策略

### 中風險項目
- Symfony Validator 套件未安裝 → 優雅降級，不強制依賴
- 複雜 Union Types 處理 → 參考研究成果，使用 oneOf

### 緩解措施
- 每個 Phase 結束時執行完整測試
- 遇到阻礙時參考 `research-symfony-attributes` 研究成果
- 保持與 Symfony 最佳實踐一致
