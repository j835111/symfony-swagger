# Spec: Attribute Extraction

## ADDED Requirements

### Requirement: 識別 Symfony 7.x Controller Attributes

研究 MUST 識別並記錄 Symfony 7.x 中所有可用於 Controller 的原生 Attributes。

#### Scenario: 列出核心 Routing Attributes

**Given** Symfony 7.x 框架
**When** 研究 Controller 可用的 Attributes
**Then** 必須記錄以下核心 Attributes：
- `#[Route]` 及其所有參數（path, name, methods, requirements, defaults, priority, locale, format, condition, host, schemes, env）
- `#[MapRequestPayload]` 及其參數（acceptFormat, validationGroups, resolver）
- `#[MapQueryParameter]` 及其參數（filter, options）
- `#[MapQueryString]` 及其參數（validationGroups）

#### Scenario: 列出擴展 Attributes

**Given** Symfony 7.x 生態系
**When** 研究常用的擴展 Attributes
**Then** 必須記錄以下 Attributes：
- `#[IsGranted]` 安全相關
- `#[Cache]` 快取相關
- `#[AsController]` 服務標記
- SensioFrameworkExtraBundle 的 `#[ParamConverter]`（若使用）

### Requirement: 提取 Attribute 結構資訊

研究成果 MUST 包含每個 Attribute 的完整結構資訊，以便後續自動化處理。

#### Scenario: 記錄 Attribute 參數

**Given** 一個 Symfony Controller Attribute
**When** 分析其結構
**Then** 必須記錄：
- Attribute 類別的完整命名空間
- 所有可用參數名稱與型別
- 參數的預設值（若有）
- 參數的用途說明

#### Scenario: 提供使用範例

**Given** 每個已識別的 Attribute
**When** 撰寫文檔
**Then** 必須提供：
- 至少一個實際使用範例
- 範例程式碼必須符合 PSR-12 標準
- 範例必須展示常見的使用情境

### Requirement: 使用 Reflection API 讀取 Attributes

研究 MUST 驗證使用 PHP Reflection API 讀取 Controller Attributes 的可行性。

#### Scenario: 讀取 Class 層級 Attributes

**Given** 一個包含 Attributes 的 Controller 類別
**When** 使用 `ReflectionClass::getAttributes()`
**Then** 能夠：
- 成功取得所有 class-level Attributes
- 獲取每個 Attribute 的實例
- 讀取 Attribute 的所有參數值

#### Scenario: 讀取 Method 層級 Attributes

**Given** 一個包含 Attributes 的 Controller 方法
**When** 使用 `ReflectionMethod::getAttributes()`
**Then** 能夠：
- 成功取得所有 method-level Attributes
- 區分不同類型的 Attributes（Route, IsGranted 等）
- 正確處理多個 Attributes 並存的情況

#### Scenario: 讀取 Parameter Attributes

**Given** Controller 方法參數包含 Attributes（如 `#[MapQueryParameter]`）
**When** 使用 `ReflectionParameter::getAttributes()`
**Then** 能夠：
- 成功取得參數層級的 Attributes
- 關聯參數名稱與其 Attributes
- 讀取參數的型別資訊（type hints）

### Requirement: 研究成果文檔化

所有研究結果 MUST 以結構化文檔形式呈現。

#### Scenario: 建立 Attributes 清單文檔

**Given** 完成的 Attributes 研究
**When** 撰寫 `docs/research/symfony-attributes.md`
**Then** 文檔必須包含：
- 至少 8 個 Symfony 7.x Attributes 的詳細說明
- 每個 Attribute 的完整參數列表
- 每個 Attribute 至少一個程式碼範例
- 使用繁體中文撰寫

#### Scenario: 提供可執行的測試案例

**Given** Reflection API 讀取研究
**When** 建立 `tests/Research/AttributeReaderTest.php`
**Then** 測試必須：
- 包含讀取 class/method/parameter Attributes 的範例
- 可透過 PHPUnit 執行
- 驗證 Attributes 資料的正確性
- 包含完整的 PHPDoc 說明
