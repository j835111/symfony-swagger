# Spec: Routing Introspection

## ADDED Requirements

### Requirement: 研究 Compile Time 路由擷取

研究 MUST 驗證在容器編譯階段（Compile Time）擷取路由資訊的方法。

#### Scenario: 使用 Compiler Pass 存取 RouteCollection

**Given** Symfony 7.x 的 DependencyInjection 系統
**When** 實作一個 CompilerPass
**Then** 必須能夠：
- 取得完整的 RouteCollection
- 遍歷所有已註冊的 Route
- 讀取每個 Route 的 path、methods、defaults、requirements
- 獲取 Route 對應的 Controller 類別與方法名稱

#### Scenario: 從 Route 定位到 Controller

**Given** RouteCollection 中的一個 Route
**When** 分析 Route 的 `_controller` default 值
**Then** 必須能夠：
- 解析 Controller 類別的完整命名空間
- 解析 Controller 方法名稱
- 建立對應的 ReflectionClass 和 ReflectionMethod 實例
- 處理不同的 Controller 定義格式（`Controller::method`、invokable controller 等）

#### Scenario: 評估 Compile Time 方法的優缺點

**Given** Compiler Pass 實作經驗
**When** 撰寫研究文檔
**Then** 必須記錄：
- 效能特性（僅在編譯時執行一次）
- 開發體驗（需要清除快取）
- 適用場景（生產環境、靜態路由）
- 程式碼範例與實作細節

### Requirement: 研究 Runtime 路由擷取

研究 MUST 驗證在執行期（Runtime）動態擷取路由資訊的方法。

#### Scenario: 使用 Router Service 獲取路由

**Given** Symfony 的 Router Service
**When** 在執行期存取路由資訊
**Then** 必須能夠：
- 注入 `RouterInterface` 服務
- 取得 RouteCollection（透過 `getRouteCollection()`）
- 遍歷並分析所有路由
- 處理動態添加的路由（若有）

#### Scenario: 實作快取機制

**Given** Runtime 路由擷取的效能考量
**When** 設計快取策略
**Then** 必須研究：
- Symfony Cache Component 的整合方式
- 快取失效的觸發時機
- 開發環境與生產環境的不同快取策略
- 快取的效能改善程度

#### Scenario: 評估 Runtime 方法的優缺點

**Given** Runtime 實作經驗
**When** 撰寫研究文檔
**Then** 必須記錄：
- 效能考量（每次請求的開銷與快取策略）
- 開發體驗（即時更新）
- 適用場景（開發環境、動態路由）
- 程式碼範例與快取實作

### Requirement: 研究 Console Command 路由擷取

研究 MUST 透過命令列工具擷取並生成路由資訊的方法。

#### Scenario: 建立路由分析命令

**Given** Symfony Console Component
**When** 建立一個 Console Command
**Then** 命令必須能夠：
- 存取 Router Service
- 分析所有路由並輸出資訊
- 支援輸出格式選項（JSON、YAML、表格）
- 整合到 `bin/console` 可執行

#### Scenario: 生成靜態文件

**Given** Console Command 實作
**When** 執行命令生成 OpenAPI 文件
**Then** 必須能夠：
- 將路由資訊轉換為結構化資料
- 輸出到指定檔案路徑
- 提供進度回饋（使用 ProgressBar 或 Logger）
- 處理錯誤情況並提供清楚的錯誤訊息

#### Scenario: 評估 Console Command 方法的優缺點

**Given** Console Command 實作經驗
**When** 撰寫研究文檔
**Then** 必須記錄：
- 可控性（手動觸發）
- CI/CD 整合（可自動化執行）
- 同步問題（可能與實際程式碼不一致）
- 程式碼範例與命令實作

### Requirement: 分析型別資訊

研究 MUST 探索如何從 Controller 方法中提取 Request/Response 型別資訊。

#### Scenario: 分析方法參數型別

**Given** Controller 方法的參數定義
**When** 使用 `ReflectionParameter::getType()`
**Then** 必須能夠：
- 識別簡單型別（string, int, bool, float, array）
- 識別類別型別（如 DTO、Entity）
- 處理 Union Types（`string|int`）
- 處理 Nullable Types（`?string` 或 `string|null`）
- 識別內建類別（如 `Request`、`Session`）

#### Scenario: 分析方法回傳型別

**Given** Controller 方法的回傳型別宣告
**When** 使用 `ReflectionMethod::getReturnType()`
**Then** 必須能夠：
- 識別 `Response` 型別及其子類別（`JsonResponse`、`RedirectResponse`）
- 識別自定義 DTO 類別
- 處理 void 回傳型別
- 處理未宣告回傳型別的情況

#### Scenario: 遞迴分析 DTO 類別

**Given** 一個 DTO 類別作為參數或回傳型別
**When** 使用 ReflectionClass 分析其結構
**Then** 必須能夠：
- 列出所有公開屬性（properties）
- 讀取每個屬性的型別宣告
- 識別巢狀的 DTO 類別並遞迴分析
- 設定遞迴深度限制避免無限迴圈
- 處理陣列型別的元素推導（需要 DocBlock）

#### Scenario: 處理 DocBlock 型別提示

**Given** 參數或屬性缺少型別宣告
**When** 讀取 PHPDoc 註解
**Then** 必須研究：
- 使用正規表達式或解析器讀取 `@param`、`@return`、`@var` 標籤
- 解析泛型語法（如 `array<string>`, `Collection<User>`）
- 處理複雜型別（如 `array<string, User[]>`）
- 評估 DocBlock 解析的可靠性與限制

### Requirement: 效能評估

研究 MUST 對不同路由擷取方法進行效能評估。

#### Scenario: Benchmark 測試

**Given** 三種路由擷取方法的實作
**When** 在包含 50+ 路由的專案中測試
**Then** 必須記錄：
- 每種方法的執行時間
- 記憶體使用量
- 快取前後的效能差異
- 在開發與生產環境的表現差異

#### Scenario: 提供效能建議

**Given** Benchmark 測試結果
**When** 撰寫 `docs/research/routing-introspection.md`
**Then** 必須包含：
- 效能比較表格或圖表
- 針對不同專案規模的建議
- 快取策略的效能影響分析
- 明確的效能最佳化建議

### Requirement: 研究成果文檔化

所有路由擷取研究結果 MUST 詳細記錄。

#### Scenario: 建立路由擷取方法比較文檔

**Given** 完成的路由擷取研究
**When** 撰寫 `docs/research/routing-introspection.md`
**Then** 文檔必須包含：
- 至少 3 種方法的詳細說明（Compiler Pass、Runtime、Console Command）
- 每種方法的完整程式碼範例
- 優缺點比較表格
- 效能評估結果
- 明確的推薦方案與理由
- 使用繁體中文撰寫

#### Scenario: 提供可執行的測試案例

**Given** 路由擷取方法研究
**When** 建立 `tests/Research/RouteAnalyzerTest.php` 和 `TypeAnalyzerTest.php`
**Then** 測試必須：
- 示範實際的路由擷取流程
- 包含型別分析的測試案例
- 可透過 PHPUnit 執行
- 驗證資料提取的正確性
- 包含詳細的註解說明

### Requirement: 調查第三方實作

研究 MUST 調查現有的第三方解決方案以獲取靈感與最佳實踐。

#### Scenario: 分析 NelmioApiDocBundle

**Given** NelmioApiDocBundle 的原始碼
**When** 研究其路由擷取實作
**Then** 必須記錄：
- 使用的核心技術與 API
- Attribute 處理方式
- 型別推導策略
- 可借鑑的設計模式

#### Scenario: 分析 ApiPlatform

**Given** ApiPlatform 的 Metadata 系統
**When** 研究其元資料收集機制
**Then** 必須記錄：
- Metadata Provider 架構
- Resource 與 Operation 的分析方式
- 可應用到本專案的概念
- 差異點與不適用之處
