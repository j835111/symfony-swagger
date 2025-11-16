# Tasks: 研究 Symfony 7.x Attributes 與 Routing 擷取

## 研究階段

### 1. Symfony 7.x Attributes 盤點
- [x] 研究 `#[Route]` Attribute 的所有參數與選項
- [x] 研究 `#[MapRequestPayload]`、`#[MapQueryParameter]`、`#[MapQueryString]` 的用法
- [x] 研究 `#[IsGranted]` 與安全相關 Attributes
- [x] 研究 `#[Cache]` 與效能相關 Attributes
- [x] 調查其他相關 Attributes（`#[AsController]` 等）
- [x] 研究第三方常用 Attributes（SensioFrameworkExtraBundle 等）
- [x] 整理每個 Attribute 的結構與可用資訊
- [x] 撰寫 `docs/research/symfony-attributes.md` 文檔

### 2. Routing 資訊擷取方法研究
- [x] 研究 Compiler Pass 方式：實作範例 CompilerPass 讀取 RouteCollection
- [x] 研究 Runtime 方式：透過 Router Service 獲取路由資訊
- [x] 研究 Console Command 方式：撰寫命令列工具讀取路由
- [x] 測試 ReflectionClass 與 ReflectionMethod 讀取 Attributes
- [x] 測試獲取 Controller 方法的參數型別（ReflectionParameter）
- [x] 測試獲取 Controller 方法的回傳型別（ReflectionType）
- [x] 比較三種方式的效能差異（benchmark）
- [x] 撰寫 `docs/research/routing-introspection.md` 文檔

### 3. 型別分析研究
- [x] 研究簡單型別（string, int, bool）的處理
- [x] 研究類別型別的遞迴分析方法
- [x] 研究陣列型別的元素推導（DocBlock 分析）
- [x] 研究 Union Types 與 Nullable Types 的處理
- [x] 研究 DTO 類別屬性的分析方法
- [x] 測試 Symfony Serializer Attributes（@Groups）的讀取
- [x] 建立型別對應到 OpenAPI Schema 的對照表

### 4. 第三方方案調查
- [x] 分析 NelmioApiDocBundle 的實作方式
- [x] 分析 ApiPlatform 的 Metadata 系統
- [x] 研究其他 OpenAPI 生成工具的做法
- [x] 整理可借鑑的設計模式與最佳實踐

### 5. 概念驗證實作
- [x] 建立 `tests/Research/` 目錄
- [x] 實作測試案例：使用 Compiler Pass 讀取路由
- [x] 實作測試案例：使用 Reflection API 讀取 Attributes
- [x] 實作測試案例：分析參數與回傳型別
- [x] 建立範例 Controller 包含各種 Attributes
- [x] 撰寫可執行的 PHPUnit 測試
- [x] 確保所有範例程式碼可正常執行

### 6. 文檔與決策
- [x] 更新 `design.md`，基於研究結果補充技術決策
- [x] 在 `design.md` 中明確推薦方案與理由
- [x] 撰寫後續實作的技術藍圖
- [x] 整理研究過程中發現的問題與限制
- [x] 記錄需要特別注意的 edge cases

### 7. 驗證與完成
- [x] 執行 `openspec validate research-symfony-attributes --strict`
- [x] 修正所有驗證錯誤
- [x] Review 所有文檔的完整性與正確性
- [x] 確認所有程式碼範例可執行
- [x] 準備向團隊展示研究成果

## 驗收檢查清單

- [x] `docs/research/symfony-attributes.md` 完成，至少包含 8 個 Attributes（已完成 12 個）
- [x] `docs/research/routing-introspection.md` 完成，比較至少 3 種方法（已完成 3 種）
- [x] `tests/Research/` 包含至少 3 個可執行的測試案例（已完成 6 個檔案）
- [x] `design.md` 包含明確的技術決策與推薦方案（已完成 7 項決策）
- [x] 通過 `openspec validate --strict` 驗證（已通過）
- [x] 所有文檔使用繁體中文撰寫（已完成）
- [x] 程式碼範例遵循 PSR-12 標準（已完成）
- [x] 研究成果可作為後續實作的可靠基礎（已完成）

## 預期輸出

1. **docs/research/symfony-attributes.md**
   - Symfony 7.x Controller Attributes 完整清單
   - 每個 Attribute 的參數說明
   - 實際使用範例程式碼

2. **docs/research/routing-introspection.md**
   - Compiler Pass 方法說明與範例
   - Runtime 方法說明與範例
   - Console Command 方法說明與範例
   - 效能比較與建議

3. **tests/Research/**
   - AttributeReaderTest.php
   - RouteAnalyzerTest.php
   - TypeAnalyzerTest.php
   - 範例 Controller 檔案

4. **design.md（更新）**
   - 基於研究結果的架構決策
   - 推薦的實作方向
   - 風險與限制說明
