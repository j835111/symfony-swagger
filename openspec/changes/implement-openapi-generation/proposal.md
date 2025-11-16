# 實作 OpenAPI 自動生成功能

## Change ID
`implement-openapi-generation`

## 為什麼需要這個變更 (Why)

目前 SymfonySwaggerBundle 僅有基本的骨架結構，`SwaggerGenerator` 服務只能產生空的 OpenAPI 框架。為了實現自動從 Symfony Controller Attributes 生成完整 OpenAPI 文檔的核心功能，我們需要基於 `research-symfony-attributes` 的研究成果，實作完整的路由分析、型別推導和文檔生成系統。

這個變更將使開發者能夠：
- 無需手動撰寫 OpenAPI 規格，自動從程式碼生成
- 確保 API 文檔與實作保持同步
- 透過 Symfony Attributes 控制 API 文檔細節
- 支援複雜的 DTO 類別與型別系統

## 變更內容 (What Changes)

### 核心功能

1. **路由分析能力 (Route Analysis)**
   - 從 Symfony Router 服務擷取所有路由資訊
   - 解析 Controller 類別與方法的 Reflection 資訊
   - 讀取與處理 PHP Attributes（#[Route]、#[MapRequestPayload] 等）

2. **型別分析與 Schema 生成 (Schema Generation)**
   - PHP 型別到 OpenAPI Schema 的對應系統
   - DTO 類別遞迴分析（支援巢狀物件）
   - 陣列型別元素推導（透過 DocBlock）
   - Union Types、Nullable Types、Enum 支援
   - Symfony Validator Constraints 轉換為 OpenAPI 驗證規則

3. **OpenAPI 文檔輸出 (OpenAPI Output)**
   - 生成符合 OpenAPI 3.1 規範的 JSON 文檔
   - Describer 模式架構（RouteDescriber、OperationDescriber、SchemaDescriber）
   - 多層快取機制（Request 快取 + Symfony Cache）
   - 環境適應策略（開發/測試/生產）

### 支援的 Symfony Attributes (Priority 1)

- ✅ `#[Route]` - 路由定義（path, methods, requirements 等）
- ✅ `#[MapQueryParameter]` - Query 參數
- ✅ `#[MapQueryString]` - Query DTO
- ✅ `#[MapRequestPayload]` - Request Body
- ✅ `#[MapUploadedFile]` - 檔案上傳

### 支援的 Symfony Attributes (Priority 2)

- ⚠️ `#[IsGranted]` - 安全性標記
- ⚠️ `#[Groups]` - 序列化群組

### PHP 型別對應

| PHP 型別 | OpenAPI Schema |
|----------|----------------|
| `string` | `type: string` |
| `int` | `type: integer, format: int32` |
| `float` | `type: number, format: float` |
| `bool` | `type: boolean` |
| `array` | `type: array` + 元素型別推導 |
| `?Type` | `nullable: true` |
| `Type1\|Type2` | `oneOf` |
| `\DateTimeInterface` | `type: string, format: date-time` |
| `BackedEnum` | `type: string/integer + enum` |
| DTO 類別 | 遞迴分析為 `object` schema |

## 影響範圍 (Impact)

### 新增的 Capabilities (Specs)

1. **route-analysis** - 路由與 Attributes 分析能力
2. **schema-generation** - 型別分析與 Schema 生成能力
3. **openapi-output** - OpenAPI 文檔輸出能力

### 影響的程式碼

- `src/Service/SwaggerGenerator.php` - 重構為完整的生成服務
- 新增 `src/Service/Describer/` - Describer 模式實作
  - `RouteDescriber.php` - 路由資訊描述器
  - `OperationDescriber.php` - 操作描述器
  - `SchemaDescriber.php` - Schema 描述器
- 新增 `src/Analyzer/` - 分析工具
  - `AttributeReader.php` - Attribute 讀取器
  - `TypeAnalyzer.php` - 型別分析器
- 新增 `src/Generator/` - 生成器
  - `OpenApiGenerator.php` - OpenAPI 生成主服務
- `src/DependencyInjection/Configuration.php` - 擴充設定選項
- `config/services.php` - 註冊新服務

### 設定選項

```yaml
symfony_swagger:
  generation_mode: auto  # auto, runtime, static
  cache:
    enabled: true
    ttl: 3600
  analysis:
    max_depth: 5  # DTO 遞迴分析最大深度
    include_internal_routes: false
```

## 相依性 (Dependencies)

- **前置條件**: `research-symfony-attributes` (已完成 ✓)
- **Symfony 版本**: 需要 Symfony 7.0+
- **PHP 版本**: PHP 8.1+
- **Composer 套件**: 無新增外部依賴

## 風險與緩解措施 (Risks)

| 風險 | 等級 | 緩解策略 |
|------|------|----------|
| Reflection 效能問題 | 🟡 中 | 多層快取 + Lazy Loading |
| 型別推導不完整 | 🟢 低 | 允許自定義 Attributes 補充 |
| DTO 循環引用 | 🟡 中 | 引用追蹤 + 最大深度限制 |
| 複雜 Union Types | 🟢 低 | 使用 oneOf 表示 |
| Symfony 版本相容性 | 🟢 低 | 明確支援 7.0+，測試多版本 |

## 時程估計

基於研究階段的評估：

- **Phase 1: 核心架構** (3-5 天)
  - Describer 基礎類別
  - AttributeReader 與 TypeAnalyzer
  - OpenApiGenerator 主服務

- **Phase 2: 完整功能** (5-7 天)
  - 所有 Priority 1 Attributes
  - DTO 遞迴分析
  - Validator Constraints 轉換

- **Phase 3: 優化與測試** (3-5 天)
  - 效能優化與 Benchmark
  - 完整測試覆蓋率 > 80%
  - 文檔與範例

**總計預估: 11-17 天**

## 驗收標準 (Acceptance Criteria)

- [ ] 支援所有 Priority 1 Symfony Attributes（5 個）
- [ ] 支援基本 PHP 型別對應（string, int, bool, float, array）
- [ ] 支援 DTO 類別遞迴分析（最大深度可設定）
- [ ] 支援 Union Types、Nullable Types、Enum
- [ ] 從 Symfony Validator Constraints 轉換驗證規則
- [ ] 實作多層快取機制（Request + Symfony Cache）
- [ ] 生成符合 OpenAPI 3.1 規範的 JSON
- [ ] 測試覆蓋率 > 80%
- [ ] 通過 PHPStan Level 8
- [ ] 通過 PHP-CS-Fixer 檢查
- [ ] 提供完整的使用文檔與範例

## 後續計畫 (Future Work)

此變更不包含以下功能（留待後續 changes）：

- Console Command 靜態生成（`swagger:generate`）
- Swagger UI 整合
- Priority 2 Attributes 支援（#[IsGranted]、#[Cache]）
- 自定義 Attributes 擴展機制
- API 版本管理
- Webhook 支援
