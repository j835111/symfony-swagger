# Symfony 7.x Attributes 與 Routing 研究成果總結

## 📋 研究目標

為實現 Symfony Swagger Bundle 的自動 OpenAPI 文檔生成功能,本研究深入探討:

1. **Symfony 7.x Controller Attributes** - 可用的 Attributes 及其包含的資訊
2. **Routing 資訊擷取** - 在不同階段擷取完整 API routing 資訊的方法
3. **型別分析** - PHP 型別與 OpenAPI Schema 的對應關係
4. **第三方實作** - NelmioApiDocBundle 等現有方案的實作方式

---

## ✅ 研究成果

### 📚 文檔產出

#### 1. [Symfony 7.x Controller Attributes 完整清單](./symfony-attributes.md)
- **內容**:12+ 個常用 Controller Attributes 的詳細說明
- **包含**:參數說明、使用範例、OpenAPI 對應關係
- **行數**:1,129 行

**涵蓋的 Attributes**:
- ✅ `#[Route]` - 路由定義(path, methods, requirements 等)
- ✅ `#[MapQueryParameter]` - Query 參數對應
- ✅ `#[MapQueryString]` - Query DTO 對應
- ✅ `#[MapRequestPayload]` - Request Body 對應
- ✅ `#[MapUploadedFile]` - 檔案上傳處理
- ✅ `#[IsGranted]` - 存取權限控制
- ✅ `#[CurrentUser]` - 當前使用者注入
- ✅ `#[Cache]` - HTTP 快取設定
- ✅ `#[Groups]` - 序列化群組
- ✅ `#[Context]` - 序列化上下文
- ✅ `#[AsController]` - Controller 標記
- ✅ `#[MapDateTime]` - 日期時間對應

#### 2. [Routing 資訊擷取方法比較](./routing-introspection.md)
- **內容**:三種擷取方法的完整分析與比較
- **包含**:實作範例、效能分析、使用場景建議
- **行數**:1,183 行

**比較的方法**:
1. **Compiler Pass** (編譯期)
   - ⚡ 效能最佳,但開發體驗較差
   - 適合生產環境靜態路由

2. **Runtime Service** (執行期) ⭐ 推薦
   - ✅ 開發體驗佳,即時更新
   - ✅ 透過快取優化效能
   - ✅ 支援動態路由

3. **Console Command** (命令列)
   - 🎯 CI/CD 整合友好
   - 📦 生成靜態檔案,無執行期開銷
   - 適合生產環境部署

### 💻 程式碼產出

#### 1. [ExampleController.php](../../tests/Research/ExampleController.php)
範例 Controller,包含各種常見的 Symfony Attributes,用於測試 Reflection API 讀取功能。

**包含的端點**:
- `GET /api/posts` - 列表(帶分頁查詢參數)
- `GET /api/posts/{id}` - 單一資源
- `POST /api/posts` - 建立(Request Body + 權限控制)
- `PUT /api/posts/{id}` - 更新
- `DELETE /api/posts/{id}` - 刪除
- `GET /api/posts/search` - 搜尋(Query DTO)

#### 2. [ExamplePostDto.php](../../tests/Research/ExamplePostDto.php) & [ExampleSearchDto.php](../../tests/Research/ExampleSearchDto.php)
範例 DTO 類別,包含 Symfony Validator Constraints,用於測試 Schema 生成。

#### 3. [AttributeReaderTest.php](../../tests/Research/AttributeReaderTest.php)
Reflection API 測試,示範如何:
- 讀取類別層級的 Attributes
- 讀取方法層級的 Attributes
- 讀取參數層級的 Attributes
- 分析型別資訊(nullable, union types 等)
- 完整分析一個 Controller 方法

**測試數量**:11 個測試案例
**程式碼行數**:278 行

#### 4. [TypeAnalyzerTest.php](../../tests/Research/TypeAnalyzerTest.php)
型別分析測試,示範如何:
- 基本型別對應(string, int, float, bool 等)
- DateTime 型別處理
- DTO 類別分析與 Schema 生成
- Nullable 型別處理
- 驗證規則轉換為 Schema constraints
- 列舉(Enum)處理

**測試數量**:8 個測試案例
**程式碼行數**:311 行

---

## 🎯 技術決策

基於研究結果,我們做出以下技術決策:

### ✅ 決策 1: 採用 Runtime Service 作為主要方式

**理由**:
- ✅ 開發體驗優先 - 即時看到變更,無需清除快取
- ✅ 實作複雜度適中 - 比 Compiler Pass 更容易實作
- ✅ 效能可接受 - 透過多層快取優化
- ✅ 靈活性高 - 支援動態路由

**快取策略**:
```
L1: Request 快取 (Instance Property)
  ↓ Miss
L2: Symfony Cache (APCu / Redis)
  ↓ Miss
L3: 重新分析並生成 (Reflection + Attributes)
```

### ✅ 決策 2: 提供 Console Command 作為輔助方案

**用途**:
- CI/CD 整合 - 部署時生成靜態文檔
- 版本控制 - 提交 OpenAPI 檔案到 Git
- 生產環境優化 - 避免執行期開銷

### ✅ 決策 3: 完整的型別分析(遞迴分析 DTO)

**支援的型別**:
- 基本型別:`string`, `int`, `float`, `bool`
- 複雜型別:`array`, `object`, DTO 類別
- PHP 8 型別:`?Type`(nullable), `Type1|Type2`(union)
- 特殊型別:`\DateTimeInterface`, `BackedEnum`

**DTO 分析策略**:
- 遞迴分析所有 public 屬性
- 從 Symfony Validator Constraints 擷取規則
- 支援 `#[Groups]` 序列化群組
- 最大遞迴深度限制(預設 5 層)
- 循環引用偵測與處理

### ✅ 決策 4: Describer 模式架構

參考 NelmioApiDocBundle,採用 **Describer 模式**:

```
OpenApiGenerator
  ├── RouteDescriber - 擷取路由資訊
  ├── OperationDescriber - 分析 Controller Attributes
  ├── SchemaDescriber - 分析 DTO 生成 Schema
  └── SecurityDescriber - 分析安全 Attributes
```

**優點**:
- 關注點分離,易於測試
- 可獨立擴展各個 Describer
- 支援 Plugin 機制

---

## 📊 統計資訊

### 文檔統計

| 文檔 | 行數 | 內容 |
|------|------|------|
| symfony-attributes.md | 1,129 | Attributes 完整清單與說明 |
| routing-introspection.md | 1,183 | Routing 擷取方法比較 |
| **總計** | **2,312** | - |

### 程式碼統計

| 檔案 | 行數 | 類型 |
|------|------|------|
| ExampleController.php | 109 | 範例 Controller |
| ExamplePostDto.php | 28 | 範例 DTO |
| ExampleSearchDto.php | 24 | 範例 DTO |
| AttributeReaderTest.php | 278 | PHPUnit 測試 |
| TypeAnalyzerTest.php | 311 | PHPUnit 測試 |
| **總計** | **750** | - |

### 涵蓋範圍

- ✅ **Symfony Attributes**: 12+ 個
- ✅ **Routing 擷取方法**: 3 種
- ✅ **測試案例**: 19 個
- ✅ **程式碼範例**: 完整的 Controller + DTO
- ✅ **型別對應表**: 10+ 種 PHP 型別

---

## 🚀 後續實作藍圖

### Phase 1: 核心架構 (3-5 天)
- [ ] 實作 `RouteDescriber` 基礎類別
- [ ] 實作 `AttributeReader` 工具
- [ ] 實作 `TypeAnalyzer` 基本型別對應
- [ ] 實作 `OpenApiGenerator` 主服務
- [ ] 建立 Symfony Cache 整合

### Phase 2: 完整功能 (5-7 天)
- [ ] 支援所有 Priority 1 Attributes (5 個核心 Attributes)
- [ ] 實作 DTO 遞迴分析
- [ ] 實作 `SchemaDescriber` 與 Schema Registry
- [ ] 支援 Symfony Validator Constraints 轉換
- [ ] 實作 Console Command

### Phase 3: 優化與擴展 (3-5 天)
- [ ] 效能優化與 Benchmark
- [ ] 支援 Priority 2 Attributes
- [ ] 實作環境適應策略
- [ ] 撰寫完整文檔與範例
- [ ] 整合測試

**總計預估: 11-17 天**

---

## 📖 如何使用本研究成果

### 1. 查看 Attributes 文檔
```bash
cat docs/research/symfony-attributes.md
```

瞭解所有可用的 Symfony 7.x Controller Attributes 及其用法。

### 2. 查看 Routing 擷取文檔
```bash
cat docs/research/routing-introspection.md
```

比較三種 Routing 擷取方法,選擇適合的實作策略。

### 3. 執行測試範例
```bash
# 安裝依賴
composer install

# 執行測試
vendor/bin/phpunit tests/Research/
```

實際運行 Reflection API 測試,驗證概念。

### 4. 查看範例程式碼
```bash
# Controller 範例
cat tests/Research/ExampleController.php

# DTO 範例
cat tests/Research/ExamplePostDto.php

# 測試範例
cat tests/Research/AttributeReaderTest.php
```

參考實際可執行的程式碼範例。

---

## 🔗 參考資源

### Symfony 官方文檔
- [Symfony Attributes Overview](https://symfony.com/doc/current/reference/attributes.html)
- [Symfony Routing](https://symfony.com/doc/current/routing.html)
- [Symfony Controller](https://symfony.com/doc/current/controller.html)
- [Symfony Serializer](https://symfony.com/doc/current/serializer.html)

### PHP 文檔
- [PHP 8 Attributes RFC](https://wiki.php.net/rfc/attributes_v2)
- [PHP Reflection API](https://www.php.net/manual/en/book.reflection.php)

### OpenAPI 規範
- [OpenAPI 3.1 Specification](https://spec.openapis.org/oas/v3.1.0)
- [OpenAPI Guide](https://learn.openapis.org/)

### 第三方實作
- [NelmioApiDocBundle](https://github.com/nelmio/NelmioApiDocBundle)
- [API Platform](https://api-platform.com/)

---

## ✅ 驗收標準達成情況

- [x] ✅ 完成 Symfony 7.x Controller Attributes 清單文檔,包含至少 8 個常用 Attributes (實際: **12+ 個**)
- [x] ✅ 完成 Routing 資訊擷取方法比較文檔,至少比較 3 種方法 (實際: **3 種完整比較**)
- [x] ✅ 提供至少 3 個可執行的概念驗證程式碼範例 (實際: **6 個檔案, 19 個測試案例**)
- [x] ✅ 基於研究結果在 `design.md` 中提出明確的技術決策建議 (實際: **7 項具體決策**)
- [x] ✅ 通過 `openspec validate research-symfony-attributes --strict` 驗證 (**已通過**)

---

## 🎉 結論

本研究成功完成了所有目標:

1. ✅ **全面瞭解 Symfony 7.x Attributes** - 12+ 個 Attributes 的完整文檔
2. ✅ **確定最佳實作策略** - Runtime Service + Console Command 混合方案
3. ✅ **建立技術基礎** - 可執行的程式碼範例與測試
4. ✅ **制定實作藍圖** - 清晰的 Phase 1-3 計畫

研究成果為後續實作 OpenAPI 自動生成功能提供了:
- 📚 詳細的技術文檔
- 💻 可參考的程式碼範例
- 🎯 明確的技術決策
- 🗺️ 完整的實作藍圖

**下一步**: 建立新的 OpenSpec Change `implement-openapi-generation`,開始實際實作。

---

**研究完成日期**: 2025-11-11
**研究時間**: 研究階段完成
**產出**: 2,312 行文檔 + 750 行程式碼
**驗證狀態**: ✅ 通過 `openspec validate --strict`
