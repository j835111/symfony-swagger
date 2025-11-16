# 設計：Symfony 7.x Attributes 與 Routing 資訊擷取架構

## 概述

本設計文檔探討如何在 Symfony 7.x Bundle 中擷取 Controller Attributes 和 Routing 資訊，以支援自動生成 OpenAPI 文檔的需求。

## 架構考量

### 1. 資訊擷取時機點

#### 選項 A：Compile Time（編譯期）
**方式**：使用 Compiler Pass 在容器編譯階段擷取資訊

**優點**：
- 效能最佳：只在容器編譯時執行一次
- 完整的路由資訊：可存取整個 RouteCollection
- 可快取結果：避免重複分析

**缺點**：
- 開發時需要清除快取才能看到變更
- 無法處理動態路由（若有）

**適用場景**：生產環境、靜態路由定義

#### 選項 B：Runtime（執行期）
**方式**：透過 Router Service 在請求時動態獲取

**優點**：
- 即時更新：開發時無需清除快取
- 支援動態路由

**缺點**：
- 效能開銷：每次請求都需要分析
- 需要實作快取機制

**適用場景**：開發環境、需要動態路由的場景

#### 選項 C：Command（命令列）
**方式**：提供 Console Command 手動生成文檔

**優點**：
- 可控性高：開發者決定何時生成
- 無執行期開銷
- 可整合到 CI/CD

**缺點**：
- 需要手動執行
- 可能與實際程式碼不同步

**適用場景**：生成靜態 OpenAPI 文件

### 2. Reflection API 使用策略

#### Controller 分析流程

```
RouteCollection
    ↓
獲取 Controller::method
    ↓
ReflectionClass + ReflectionMethod
    ↓
getAttributes() → 獲取所有 Attributes
    ↓
分析參數型別（getParameters()）
    ↓
分析回傳型別（getReturnType()）
```

#### 型別資訊處理

- **簡單型別**：string, int, bool, float → 直接對應 OpenAPI 型別
- **類別型別**：透過 ReflectionClass 遞迴分析屬性
- **陣列型別**：需要透過 DocBlock 或泛型註解推導元素型別
- **Union Types**：PHP 8.0+ 支援，需要處理多型別情況
- **Nullable Types**：對應 OpenAPI 的 nullable

### 3. Attributes 優先順序

#### 核心 Routing Attributes
1. `#[Route]` - 最基本，必須支援
2. `#[MapRequestPayload]` - Request Body 分析（Symfony 6.3+）
3. `#[MapQueryParameter]` - Query 參數分析（Symfony 6.3+）
4. `#[MapQueryString]` - Query String 整體對應（Symfony 6.3+）

#### 擴展 Attributes
5. `#[IsGranted]` - 安全性標記（可對應 OpenAPI security）
6. `#[Cache]` - 快取提示
7. 自定義 Attributes - 使用者定義的 OpenAPI 擴展

### 4. 架構設計建議

#### 元件結構

```
AttributeReader
├── 職責：讀取 Controller Attributes
├── 輸入：ReflectionClass, ReflectionMethod
└── 輸出：AttributeCollection

RouteAnalyzer
├── 職責：分析 Route 定義
├── 輸入：RouteCollection
└── 輸出：RouteInfo[]

TypeAnalyzer
├── 職責：分析 PHP 型別並轉換為 OpenAPI Schema
├── 輸入：ReflectionType, ReflectionClass
└── 輸出：OpenAPI Schema

OpenApiGenerator
├── 職責：整合以上資訊生成 OpenAPI 文檔
├── 輸入：RouteInfo[], AttributeCollection[]
└── 輸出：OpenAPI JSON/YAML
```

## 研究重點

### 需要驗證的技術問題

1. **ReflectionAttribute API 的限制**
   - 能否正確讀取巢狀 Attributes？
   - 如何處理 Attribute 的預設值？

2. **型別推導的精確度**
   - 陣列元素型別如何推導？（需要 DocBlock？）
   - DTO 類別的屬性如何遞迴分析？
   - Symfony Serializer 的 @Groups 如何影響？

3. **效能考量**
   - Reflection 操作的效能開銷
   - 快取策略的有效性
   - 大型專案（數百個 API）的處理時間

4. **第三方整合**
   - NelmioApiDocBundle 的實作方式
   - ApiPlatform 的 Metadata 系統
   - Symfony UX 的 LiveComponent Attributes

## 決策點

研究完成後需要決定：

1. **主要擷取策略**：選擇 Compile Time、Runtime 或 Command，或提供多種模式
2. **型別分析深度**：是否遞迴分析所有 DTO 類別，或僅分析第一層
3. **快取機制**：使用 Symfony Cache、檔案快取或無快取
4. **擴展性設計**：如何支援使用者自定義 Attributes 對應到 OpenAPI 擴展

---

## 研究結果與技術決策

### ✅ 決策一:採用 Runtime Service 作為主要方式

**理由**:
- ✅ **開發體驗優先**:開發時無需清除快取,即時看到變更
- ✅ **實作複雜度適中**:相比 Compiler Pass 更容易實作和調試
- ✅ **效能可接受**:透過多層快取策略(Request + Symfony Cache)優化
- ✅ **靈活性高**:支援動態路由,未來擴展性佳

**實作細節**:
```php
// 主要服務架構
OpenApiGenerator (Runtime Service)
  ├── RouterInterface (注入)
  ├── CacheItemPoolInterface (快取)
  └── AttributeReader + TypeAnalyzer (分析工具)
```

**快取策略**:
- **開發環境**:60 秒 TTL
- **測試環境**:3600 秒 TTL (1 小時)
- **生產環境**:86400 秒 TTL (24 小時) 或使用靜態檔案

---

### ✅ 決策二:提供 Console Command 作為輔助方案

**理由**:
- ✅ **CI/CD 整合**:可在部署時自動生成靜態文檔
- ✅ **版本控制**:生成的 OpenAPI 檔案可提交到 Git
- ✅ **生產環境優化**:完全避免執行期開銷

**使用場景**:
```bash
# 開發階段:使用 Runtime Service (動態生成)
GET /api/doc.json

# 部署階段:使用 Console Command (靜態生成)
php bin/console swagger:generate -o public/api-doc.json
```

---

### ✅ 決策三:完整的型別分析(遞迴分析 DTO)

**支援的型別對應**:

| PHP 型別 | OpenAPI Schema | 備註 |
|----------|----------------|------|
| `string` | `type: string` | |
| `int` | `type: integer, format: int32` | |
| `float` | `type: number, format: float` | |
| `bool` | `type: boolean` | |
| `array` | `type: array` | 需推導元素型別 |
| `?Type` | `nullable: true` | PHP 8 Nullable |
| `Type1\|Type2` | `oneOf` | PHP 8 Union Types |
| `\DateTimeInterface` | `type: string, format: date-time` | |
| `BackedEnum` | `type: string/integer + enum` | |
| DTO 類別 | `$ref` 或 inline `object` | 遞迴分析 |

**DTO 分析策略**:
- ✅ 遞迴分析所有 public 屬性
- ✅ 從 Symfony Validator Constraints 擷取規則
- ✅ 支援 `#[Groups]` 序列化群組
- ✅ 最大遞迴深度限制(預設 5 層,可設定)
- ✅ 循環引用偵測與處理

---

### ✅ 決策四:支援的 Symfony Attributes 清單

#### 必須支援(Priority 1)
1. ✅ `#[Route]` - 路由定義(path, methods, requirements 等)
2. ✅ `#[MapQueryParameter]` - Query 參數
3. ✅ `#[MapQueryString]` - Query DTO
4. ✅ `#[MapRequestPayload]` - Request Body
5. ✅ `#[MapUploadedFile]` - 檔案上傳

#### 應該支援(Priority 2)
6. ✅ `#[IsGranted]` - 安全性標記
7. ✅ `#[Groups]` - 序列化群組(影響 Schema)
8. ⚠️ `#[Cache]` - 快取資訊(可選,標註用)
9. ⚠️ `#[CurrentUser]` - 暗示需要認證

#### 可選支援(Priority 3)
10. ⚠️ 自定義 OpenAPI Attributes (未來擴展)

---

### ✅ 決策五:Describer 模式架構

參考 NelmioApiDocBundle,採用 **Describer 模式**分離關注點:

```
OpenApiGenerator
  ├── RouteDescriber
  │     ├── 職責:從 RouteCollection 擷取路由資訊
  │     └── 輸出:PathItem[] (OpenAPI paths)
  │
  ├── OperationDescriber
  │     ├── 職責:分析 Controller Method Attributes
  │     └── 輸出:Operation (parameters, requestBody, responses)
  │
  ├── SchemaDescriber
  │     ├── 職責:分析 DTO 類別生成 Schema
  │     └── 輸出:Schema (components/schemas)
  │
  └── SecurityDescriber
        ├── 職責:分析 #[IsGranted] 等安全 Attributes
        └── 輸出:SecurityRequirement
```

**優點**:
- 關注點分離,易於測試
- 可獨立擴展各個 Describer
- 支援自定義 Describer (Plugin 機制)

---

### ✅ 決策六:快取與效能優化策略

#### 多層快取架構

```php
L1: Request 快取 (Instance Property)
  ↓ Miss
L2: Symfony Cache (APCu / Redis)
  ↓ Miss
L3: 重新分析並生成 (Reflection + Attributes)
```

#### 效能優化措施

1. **Lazy Loading**:僅在首次存取 `/api/doc.json` 時生成
2. **部分更新**:僅重新分析變更的 Controller(未來優化)
3. **平行處理**:可使用 Symfony Messenger 非同步生成(未來優化)

#### 預期效能指標

- **小型專案**(< 50 個端點):< 100ms (首次),< 5ms (快取)
- **中型專案**(50-200 個端點):< 500ms (首次),< 10ms (快取)
- **大型專案**(> 200 個端點):< 2s (首次),< 20ms (快取)

---

### ✅ 決策七:環境適應策略

```yaml
# config/packages/symfony_swagger.yaml

symfony_swagger:
  # 根據環境自動選擇策略
  generation_mode: '%env(default:auto:SWAGGER_GENERATION_MODE)%'
  # auto: 自動選擇 (dev=runtime, prod=static)
  # runtime: 執行期動態生成
  # static: 使用預先生成的檔案

  cache:
    enabled: true
    ttl: '%env(default:3600:int:SWAGGER_CACHE_TTL)%'

  analysis:
    max_depth: 5  # DTO 遞迴分析最大深度
    include_internal_routes: false  # 是否包含 _ 開頭的內部路由
```

---

## 後續實作藍圖

### Phase 1: 核心架構 (預估 3-5 天)
- [ ] 實作 `RouteDescriber` 基礎類別
- [ ] 實作 `AttributeReader` 工具
- [ ] 實作 `TypeAnalyzer` 基本型別對應
- [ ] 實作 `OpenApiGenerator` 主服務
- [ ] 建立基本的 Symfony Cache 整合

### Phase 2: 完整功能 (預估 5-7 天)
- [ ] 支援所有 Priority 1 Attributes
- [ ] 實作 DTO 遞迴分析
- [ ] 實作 `SchemaDescriber` 與 Schema Registry
- [ ] 支援 Symfony Validator Constraints 轉換
- [ ] 實作 Console Command

### Phase 3: 優化與擴展 (預估 3-5 天)
- [ ] 效能優化與 Benchmark
- [ ] 支援 Priority 2 Attributes
- [ ] 實作環境適應策略
- [ ] 撰寫完整文檔與範例
- [ ] 整合測試

**總計預估: 11-17 天**

---

## 技術風險評估與緩解

| 風險 | 等級 | 緩解策略 | 狀態 |
|------|------|----------|------|
| Reflection 效能問題 | 🟡 中 | 多層快取 + Lazy Loading | ✅ 已規劃 |
| 型別推導不完整 | 🟢 低 | 允許自定義 Attributes 補充 | ✅ 已規劃 |
| DTO 循環引用 | 🟡 中 | 引用追蹤 + 最大深度限制 | ✅ 已規劃 |
| 複雜 Union Types | 🟡 中 | 使用 oneOf/anyOf 表示 | ✅ 已研究 |
| Symfony 版本相容性 | 🟢 低 | 明確支援 7.0+,測試多版本 | ✅ 已確認 |
| 大型專案效能 | 🟡 中 | Console Command 靜態生成 | ✅ 已規劃 |

---

## 驗收標準更新

基於研究結果,更新驗收標準:

✅ **研究階段**
- [x] 完成 Symfony 7.x Attributes 完整清單(12+ 個)
- [x] 完成 Routing 擷取方法比較(3 種方法)
- [x] 提供可執行的概念驗證程式碼(6 個檔案)
- [x] 明確的技術決策與推薦方案

🎯 **後續實作階段** (下一個 OpenSpec Change)
- [ ] Runtime Service 架構實作
- [ ] 支援核心 5 個 Attributes
- [ ] DTO 分析與 Schema 生成
- [ ] Console Command 實作
- [ ] 完整測試覆蓋率 > 80%

## 參考資料

- [Symfony 7.x Routing Documentation](https://symfony.com/doc/current/routing.html)
- [PHP 8 Attributes RFC](https://wiki.php.net/rfc/attributes_v2)
- [OpenAPI 3.1 Specification](https://spec.openapis.org/oas/v3.1.0)
- [NelmioApiDocBundle Source Code](https://github.com/nelmio/NelmioApiDocBundle)
- [ApiPlatform Metadata System](https://api-platform.com/docs/core/extending/)

## 風險緩解

| 風險 | 緩解策略 |
|------|----------|
| 型別推導不完整 | 允許手動註解補充，提供自定義 Attribute |
| 效能問題 | 實作多層快取機制，提供 lazy loading |
| Symfony 版本更新 | 僅專注 7.x，明確標示最低版本需求 |
| 複雜 DTO 結構 | 提供深度控制選項，避免無限遞迴 |
