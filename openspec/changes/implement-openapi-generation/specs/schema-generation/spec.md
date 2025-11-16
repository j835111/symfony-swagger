# Capability: Schema Generation

型別分析與 OpenAPI Schema 生成能力

## ADDED Requirements

### Requirement: Built-in Type Mapping

系統 SHALL 能夠將 PHP 內建型別對應到 OpenAPI Schema 型別定義。

#### Scenario: 對應基本純量型別

- **GIVEN** PHP 型別為 `string`、`int`、`float`、`bool` 之一
- **WHEN** TypeAnalyzer 分析該型別
- **THEN** 系統生成對應的 OpenAPI schema：
  - `string` → `{type: "string"}`
  - `int` → `{type: "integer", format: "int32"}`
  - `float` → `{type: "number", format: "float"}`
  - `bool` → `{type: "boolean"}`

#### Scenario: 對應陣列型別

- **GIVEN** PHP 型別為 `array`
- **WHEN** 系統分析該型別且無法推導元素型別
- **THEN** 系統生成 `{type: "array"}`
- **AND** 不包含 `items` 定義

#### Scenario: 從 DocBlock 推導陣列元素型別

- **GIVEN** 屬性宣告為 `array` 且 DocBlock 為 `@var int[]`
- **WHEN** 系統分析該屬性
- **THEN** 系統從 DocBlock 提取元素型別為 `int`
- **AND** 生成 `{type: "array", items: {type: "integer", format: "int32"}}`

### Requirement: Nullable Type Handling

系統 SHALL 能夠辨識 Nullable Types 並在 Schema 中正確表示。

#### Scenario: 處理 Nullable 型別（PHP 8.0+）

- **GIVEN** 屬性型別宣告為 `?string`
- **WHEN** 系統分析該型別
- **THEN** 系統生成 `{type: "string", nullable: true}`

#### Scenario: 處理 Union Type 包含 null

- **GIVEN** 屬性型別為 `string|null`
- **WHEN** 系統分析該 Union Type
- **THEN** 系統生成 `{type: "string", nullable: true}`

### Requirement: Union Type Handling

系統 SHALL 能夠處理 PHP 8.0+ Union Types 並使用 OpenAPI `oneOf` 表示。

#### Scenario: 處理簡單 Union Type

- **GIVEN** 屬性型別為 `string|int`
- **WHEN** 系統分析該型別
- **THEN** 系統生成 `{oneOf: [{type: "string"}, {type: "integer", format: "int32"}]}`

#### Scenario: 處理 Union Type 包含類別

- **GIVEN** 屬性型別為 `PostDto|CommentDto`
- **WHEN** 系統分析該型別
- **THEN** 系統遞迴分析兩個 DTO
- **AND** 生成 `{oneOf: [{$ref: "#/components/schemas/PostDto"}, {$ref: "#/components/schemas/CommentDto"}]}`

### Requirement: Special Class Type Mapping

系統 SHALL 能夠識別常見 PHP 類別並對應到適當的 OpenAPI 格式。

#### Scenario: 對應 DateTime 類別

- **GIVEN** 屬性型別為 `\DateTime` 或 `\DateTimeInterface` 或 `\DateTimeImmutable`
- **WHEN** 系統分析該型別
- **THEN** 系統生成 `{type: "string", format: "date-time"}`

#### Scenario: 對應 BackedEnum

- **GIVEN** 屬性型別為 `StatusEnum` 且該 Enum 為 `BackedEnum`
- **AND** Enum 定義為 `enum StatusEnum: string { case DRAFT = 'draft'; case PUBLISHED = 'published'; }`
- **WHEN** 系統分析該型別
- **THEN** 系統生成 `{type: "string", enum: ["draft", "published"]}`

### Requirement: DTO Class Recursive Analysis

系統 SHALL 能夠遞迴分析 DTO 類別的所有 public 屬性並生成完整的 Object Schema。

#### Scenario: 分析簡單 DTO

- **GIVEN** DTO 類別定義：
  ```php
  class PostDto {
      public int $id;
      public string $title;
      public ?string $content;
  }
  ```
- **WHEN** SchemaDescriber 分析該類別
- **THEN** 系統生成包含三個屬性的 Object Schema
- **AND** 每個屬性包含正確的型別定義
- **AND** `content` 標記為 `nullable: true`

#### Scenario: 分析巢狀 DTO

- **GIVEN** DTO 包含另一個 DTO 屬性：
  ```php
  class PostDto {
      public int $id;
      public AuthorDto $author;
  }
  ```
- **WHEN** 系統分析 `PostDto`
- **THEN** 系統遞迴分析 `AuthorDto`
- **AND** 在 `author` 屬性使用 `{$ref: "#/components/schemas/AuthorDto"}`
- **AND** 兩個 Schema 都註冊到 SchemaRegistry

#### Scenario: 分析包含陣列 DTO 的屬性

- **GIVEN** DTO 屬性定義為：
  ```php
  /** @var CommentDto[] */
  public array $comments;
  ```
- **WHEN** 系統分析該屬性
- **THEN** 系統從 DocBlock 提取元素型別為 `CommentDto`
- **AND** 生成 `{type: "array", items: {$ref: "#/components/schemas/CommentDto"}}`
- **AND** 觸發 `CommentDto` 的遞迴分析

### Requirement: Circular Reference Detection

系統 SHALL 能夠偵測並處理 DTO 之間的循環引用，避免無限遞迴。

#### Scenario: 偵測直接循環引用

- **GIVEN** DTO 定義：
  ```php
  class NodeDto {
      public int $id;
      public ?NodeDto $parent;
  }
  ```
- **WHEN** 系統分析 `NodeDto` 並遇到 `parent` 屬性
- **THEN** 系統偵測到正在分析的類別再次出現
- **AND** 直接使用 `{$ref: "#/components/schemas/NodeDto"}` 不進行遞迴
- **AND** 不會進入無限迴圈

#### Scenario: 偵測間接循環引用

- **GIVEN** 三個 DTO 形成循環：`A → B → C → A`
- **WHEN** 系統從 A 開始分析
- **THEN** 系統維護分析堆疊 `[A, B, C]`
- **AND** 當再次遇到 A 時中斷遞迴
- **AND** 使用 `$ref` 參照已定義的 Schema

### Requirement: Maximum Depth Limit

系統 SHALL 限制 DTO 遞迴分析的最大深度，防止過深的巢狀結構導致效能問題。

#### Scenario: 強制執行最大深度限制

- **GIVEN** 設定 `max_depth` 為 5
- **AND** 一個 DTO 巢狀超過 5 層
- **WHEN** 系統分析到第 6 層
- **THEN** 系統停止遞迴分析
- **AND** 回傳 `{type: "object", additionalProperties: true}`
- **AND** 記錄 Warning 日誌說明達到最大深度

### Requirement: Symfony Validator Constraints Integration

系統 SHALL 能夠讀取 Symfony Validator Constraints 並轉換為對應的 OpenAPI 驗證規則。

#### Scenario: 轉換 Length Constraint

- **GIVEN** 屬性標註 `#[Length(min: 3, max: 100)]`
- **WHEN** 系統提取該屬性的 Constraints
- **THEN** Schema 包含 `minLength: 3` 和 `maxLength: 100`

#### Scenario: 轉換 Range Constraint

- **GIVEN** 屬性標註 `#[Range(min: 1, max: 100)]`
- **WHEN** 系統轉換 Constraints
- **THEN** Schema 包含 `minimum: 1` 和 `maximum: 100`

#### Scenario: 轉換 Email Constraint

- **GIVEN** 屬性標註 `#[Email]`
- **WHEN** 系統處理該 Constraint
- **THEN** Schema 包含 `format: "email"`

#### Scenario: 轉換 Choice Constraint

- **GIVEN** 屬性標註 `#[Choice(choices: ['draft', 'published', 'archived'])]`
- **WHEN** 系統轉換該 Constraint
- **THEN** Schema 包含 `enum: ["draft", "published", "archived"]`

#### Scenario: 轉換 NotBlank Constraint

- **GIVEN** 屬性標註 `#[NotBlank]`
- **WHEN** 系統處理該 Constraint
- **THEN** Schema 包含 `minLength: 1`

### Requirement: Schema Registry Management

系統 SHALL 維護一個全域 Schema Registry，確保相同類別只分析一次並使用 `$ref` 參照。

#### Scenario: 註冊新 Schema

- **GIVEN** 系統首次分析 `PostDto`
- **WHEN** SchemaDescriber 完成分析
- **THEN** 系統將 Schema 註冊到 Registry
- **AND** 回傳 `$ref` 路徑 `#/components/schemas/PostDto`

#### Scenario: 重複使用已註冊 Schema

- **GIVEN** `PostDto` 已在 Registry 中註冊
- **AND** 另一個 DTO 屬性參照 `PostDto`
- **WHEN** 系統分析該屬性
- **THEN** 系統檢查 Registry 發現已存在
- **AND** 直接回傳 `$ref` 不重複分析

#### Scenario: 產生 Schema Name

- **GIVEN** 完整類別名稱為 `App\DTO\Blog\PostDto`
- **WHEN** 系統生成 Schema 名稱
- **THEN** 使用短類別名稱 `PostDto`
- **AND** 如果有衝突則附加命名空間前綴

### Requirement: Type Analysis Error Handling

系統 SHALL 優雅處理型別分析過程中的錯誤，使用降級策略確保文檔生成不中斷。

#### Scenario: 處理缺少型別提示的屬性

- **GIVEN** DTO 屬性未宣告型別：`public $data;`
- **WHEN** 系統分析該屬性
- **THEN** 系統檢查 DocBlock 尋找 `@var` 標註
- **AND** 若無法推導則回退為 `{type: "string"}`
- **AND** 記錄 Info 日誌建議新增型別提示

#### Scenario: 處理無法分析的類別

- **GIVEN** 屬性型別為一個不存在或無法載入的類別
- **WHEN** 系統嘗試建立 ReflectionClass
- **THEN** 系統捕捉 ReflectionException
- **AND** 回傳 `{type: "object", additionalProperties: true}`
- **AND** 記錄 Warning 日誌說明無法分析的類別

### Requirement: Serialization Groups Support

系統 SHALL 能夠識別 Symfony Serializer 的 `#[Groups]` Attribute，根據序列化群組過濾屬性。

#### Scenario: 根據 Groups 過濾屬性

- **GIVEN** DTO 屬性標註 `#[Groups(['read'])]`
- **AND** 另一屬性標註 `#[Groups(['write'])]`
- **AND** 設定目前分析的 context 為 `['groups' => ['read']]`
- **WHEN** SchemaDescriber 分析該 DTO
- **THEN** 僅包含標註 `read` 群組的屬性
- **AND** `write` 群組的屬性被排除

#### Scenario: 無 Groups 標註時的預設行為

- **GIVEN** DTO 某些屬性有 `#[Groups]` 標註，某些沒有
- **AND** 分析時未指定 context groups
- **WHEN** 系統分析該 DTO
- **THEN** 包含所有屬性（無論是否有 Groups 標註）
