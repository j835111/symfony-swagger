# 設計文檔：OpenAPI 自動生成系統

## 背景與目標 (Context)

基於 `research-symfony-attributes` 的研究成果，本設計實作一個從 Symfony Controller Attributes 自動生成 OpenAPI 3.1 文檔的系統。

### 目標 (Goals)

1. **自動化**: 從 Symfony Attributes 自動生成 OpenAPI 文檔，無需手動撰寫
2. **準確性**: 透過 Reflection 與型別分析確保文檔與實作一致
3. **效能**: 使用多層快取避免重複分析的開銷
4. **擴展性**: 支援未來新增 Attributes 與自定義擴展

### 非目標 (Non-Goals)

- ❌ Swagger UI 整合（留待後續變更）
- ❌ Console Command 靜態生成（留待後續變更）
- ❌ Compiler Pass 編譯期生成（研究結果顯示不適合開發環境）
- ❌ 多版本 API 管理（留待後續變更）

## 架構設計 (Architecture)

### 整體架構

```
OpenApiGenerator (主服務)
  │
  ├─> RouteDescriber
  │     ├─ 職責: 從 RouteCollection 擷取路由資訊
  │     ├─ 輸入: RouterInterface
  │     └─ 輸出: PathItem[] (OpenAPI paths)
  │
  ├─> OperationDescriber
  │     ├─ 職責: 分析 Controller Method Attributes
  │     ├─ 使用: AttributeReader
  │     └─ 輸出: Operation (parameters, requestBody, responses)
  │
  ├─> SchemaDescriber
  │     ├─ 職責: 分析 DTO 類別生成 Schema
  │     ├─ 使用: TypeAnalyzer
  │     └─ 輸出: Schema (components/schemas)
  │
  └─> CacheAdapter
        ├─ L1: Request 快取 (Instance Property)
        └─ L2: Symfony Cache (APCu / Redis)
```

### Describer 模式

參考 NelmioApiDocBundle，採用 Describer 模式分離關注點：

#### RouteDescriber
- **輸入**: `RouterInterface`
- **處理**: 迭代 `RouteCollection`，過濾 API 路由
- **輸出**: `PathItem[]` 包含路徑與 HTTP 方法

#### OperationDescriber
- **輸入**: `Route`, `ReflectionMethod`
- **處理**:
  - 讀取 `#[Route]` 參數
  - 分析 `#[MapRequestPayload]`、`#[MapQueryParameter]` 等
  - 推導 parameters、requestBody、responses
- **輸出**: `Operation` 物件

#### SchemaDescriber
- **輸入**: `ReflectionClass` (DTO)
- **處理**:
  - 遞迴分析所有 public 屬性
  - 讀取 Symfony Validator Constraints
  - 處理 `#[Groups]` 序列化群組
  - 偵測循環引用
- **輸出**: `Schema` 物件

## 關鍵技術決策 (Decisions)

### 決策 1: Runtime Service 為主要實作方式

**理由**:
- ✅ 開發體驗優先：無需清除快取即時看到變更
- ✅ 實作複雜度適中：相比 Compiler Pass 更容易調試
- ✅ 靈活性高：支援動態路由與條件式 Attributes

**實作細節**:
```php
class OpenApiGenerator
{
    public function __construct(
        private RouterInterface $router,
        private CacheItemPoolInterface $cache,
        private AttributeReader $attributeReader,
        private TypeAnalyzer $typeAnalyzer,
        private array $config = []
    ) {}

    public function generate(): array
    {
        $cacheKey = 'openapi_doc_' . md5(serialize($this->config));

        return $this->cache->get($cacheKey, function() {
            return $this->doGenerate();
        });
    }
}
```

### 決策 2: 多層快取策略

**快取層級**:
```
L1: Request 快取 (Instance Property)
  - 生命週期: 單次請求
  - 用途: 避免同一請求重複分析

L2: Symfony Cache (APCu/Redis/Filesystem)
  - TTL: 依環境調整 (dev=60s, test=3600s, prod=86400s)
  - 用途: 跨請求共享分析結果
```

**快取失效策略**:
- 開發環境: 短 TTL (60 秒) 自動過期
- 測試/生產環境: 長 TTL + 手動清除快取

### 決策 3: 型別分析深度與限制

**支援的型別對應**:

| PHP 型別 | OpenAPI Schema | 實作方式 |
|----------|----------------|----------|
| `string` | `type: string` | ReflectionType::getName() |
| `int` | `type: integer, format: int32` | 內建型別對應 |
| `float` | `type: number, format: float` | 內建型別對應 |
| `bool` | `type: boolean` | 內建型別對應 |
| `array` | `type: array` | DocBlock 分析元素型別 |
| `?Type` | `nullable: true` | ReflectionType::allowsNull() |
| `Type1\|Type2` | `oneOf: [...]` | ReflectionUnionType |
| `\DateTimeInterface` | `type: string, format: date-time` | 特殊類別對應 |
| `BackedEnum` | `type: string/integer, enum: [...]` | Enum::cases() |
| DTO 類別 | `$ref` 或 inline `object` | 遞迴分析 |

**安全限制**:
- 最大遞迴深度: 5 層（可設定）
- 循環引用偵測: 使用 `$analyzedClasses` 追蹤棧
- 超時保護: 單一 DTO 分析限時 1 秒

### 決策 4: Symfony Validator Constraints 轉換

**支援的 Constraints**:

| Symfony Constraint | OpenAPI Property |
|--------------------|------------------|
| `#[NotBlank]` | `minLength: 1` |
| `#[Length(min, max)]` | `minLength, maxLength` |
| `#[Range(min, max)]` | `minimum, maximum` |
| `#[Regex(pattern)]` | `pattern` |
| `#[Email]` | `format: email` |
| `#[Url]` | `format: uri` |
| `#[Choice(choices)]` | `enum` |
| `#[Count(min, max)]` | `minItems, maxItems` |

**實作方式**:
```php
class ConstraintToSchemaConverter
{
    public function convert(Attribute $constraint): array
    {
        return match($constraint->getName()) {
            Length::class => [
                'minLength' => $constraint->getArguments()['min'] ?? null,
                'maxLength' => $constraint->getArguments()['max'] ?? null,
            ],
            // ...
        };
    }
}
```

### 決策 5: Schema Registry 與參照管理

**策略**: 使用 `$ref` 避免重複定義

```php
class SchemaRegistry
{
    private array $schemas = [];

    public function register(string $className, array $schema): string
    {
        $schemaName = $this->getSchemaName($className);
        $this->schemas[$schemaName] = $schema;
        return "#/components/schemas/{$schemaName}";
    }

    public function getSchemas(): array
    {
        return $this->schemas;
    }

    private function getSchemaName(string $className): string
    {
        // App\DTO\UserDto -> UserDto
        return (new \ReflectionClass($className))->getShortName();
    }
}
```

### 決策 6: 環境適應策略

```yaml
# config/packages/symfony_swagger.yaml

symfony_swagger:
  # 根據環境自動調整
  generation_mode: '%env(default:auto:SWAGGER_GENERATION_MODE)%'
  # auto: dev=runtime, prod=runtime (with long cache)
  # runtime: 強制 Runtime Service

  cache:
    enabled: true
    ttl: '%env(default:ttl_auto:int:SWAGGER_CACHE_TTL)%'
    # ttl_auto: dev=60, test=3600, prod=86400

  analysis:
    max_depth: 5
    include_internal_routes: false  # 排除 _ 開頭的路由

  info:
    title: 'API Documentation'
    version: '1.0.0'
    description: ''

  servers:
    - url: '%env(API_BASE_URL)%'
      description: 'API Server'
```

## 類別設計 (Class Design)

### 核心服務類別

#### OpenApiGenerator

```php
namespace SymfonySwagger\Service;

class OpenApiGenerator
{
    public function __construct(
        private RouterInterface $router,
        private CacheItemPoolInterface $cache,
        private AttributeReader $attributeReader,
        private TypeAnalyzer $typeAnalyzer,
        private SchemaRegistry $schemaRegistry,
        private array $config
    ) {}

    public function generate(): array;
    private function generatePaths(): array;
    private function generateSchemas(): array;
}
```

#### AttributeReader

```php
namespace SymfonySwagger\Analyzer;

class AttributeReader
{
    public function readRouteAttribute(ReflectionMethod $method): ?Route;
    public function readRequestAttributes(ReflectionMethod $method): array;
    public function readSecurityAttributes(ReflectionMethod $method): array;
    public function getParametersFromAttributes(ReflectionMethod $method): array;
}
```

#### TypeAnalyzer

```php
namespace SymfonySwagger\Analyzer;

class TypeAnalyzer
{
    public function __construct(
        private int $maxDepth = 5
    ) {}

    public function analyze(
        ReflectionType|ReflectionClass $type,
        int $depth = 0,
        array $context = []
    ): array;

    private function analyzeBuiltinType(ReflectionNamedType $type): array;
    private function analyzeClassType(ReflectionClass $class, int $depth, array $context): array;
    private function analyzeUnionType(ReflectionUnionType $type, int $depth, array $context): array;
    private function extractFromDocBlock(ReflectionProperty|ReflectionParameter $reflection): ?string;
}
```

#### SchemaRegistry

```php
namespace SymfonySwagger\Service;

class SchemaRegistry
{
    private array $schemas = [];
    private array $analyzing = [];  // 循環引用偵測

    public function register(string $className, array $schema): string;
    public function has(string $className): bool;
    public function getReference(string $className): string;
    public function getSchemas(): array;
    public function isAnalyzing(string $className): bool;
    public function markAnalyzing(string $className): void;
    public function unmarkAnalyzing(string $className): void;
}
```

### Describer 類別

#### RouteDescriber

```php
namespace SymfonySwagger\Service\Describer;

class RouteDescriber
{
    public function describe(RouterInterface $router, array $config): array;
    private function shouldIncludeRoute(Route $route, array $config): bool;
    private function extractControllerCallable(Route $route): ?array;
}
```

#### OperationDescriber

```php
namespace SymfonySwagger\Service\Describer;

class OperationDescriber
{
    public function __construct(
        private AttributeReader $attributeReader,
        private TypeAnalyzer $typeAnalyzer
    ) {}

    public function describe(ReflectionMethod $method, Route $route): array;
    private function describeParameters(ReflectionMethod $method): array;
    private function describeRequestBody(ReflectionMethod $method): ?array;
    private function describeResponses(ReflectionMethod $method): array;
}
```

#### SchemaDescriber

```php
namespace SymfonySwagger\Service\Describer;

class SchemaDescriber
{
    public function __construct(
        private TypeAnalyzer $typeAnalyzer,
        private SchemaRegistry $schemaRegistry
    ) {}

    public function describe(ReflectionClass $class, int $depth = 0): array;
    private function describeProperties(ReflectionClass $class, int $depth): array;
    private function extractConstraints(ReflectionProperty $property): array;
}
```

## 資料流程 (Data Flow)

```
1. 使用者請求 OpenApiGenerator::generate()
   ↓
2. 檢查快取 (L1 + L2)
   ↓ (快取 Miss)
3. RouteDescriber::describe()
   ├─ 從 Router 取得 RouteCollection
   ├─ 過濾內部路由 (_profiler, _wdt 等)
   └─ 解析 Controller callable
   ↓
4. 對每個路由執行 OperationDescriber::describe()
   ├─ AttributeReader 讀取 Attributes
   ├─ 分析方法參數 (parameters)
   │   └─ TypeAnalyzer 推導型別
   ├─ 分析 Request Body (requestBody)
   │   ├─ 偵測 #[MapRequestPayload]
   │   └─ SchemaDescriber 分析 DTO
   └─ 分析回應 (responses)
       └─ 從回傳型別推導 Schema
   ↓
5. SchemaRegistry 收集所有 Schema
   ├─ 去重複（相同類別只分析一次）
   └─ 生成 $ref 參照
   ↓
6. 組裝 OpenAPI 文檔
   ├─ info (從 config)
   ├─ servers (從 config)
   ├─ paths (從 Describers)
   └─ components.schemas (從 SchemaRegistry)
   ↓
7. 快取結果並回傳
```

## 錯誤處理 (Error Handling)

### 分析錯誤

```php
class AnalysisException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $className,
        public readonly ?string $propertyName = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
```

**錯誤情境**:
- DTO 類別不存在 → 記錄 Warning，跳過該屬性
- 循環引用偵測 → 使用 `$ref` 中斷循環
- Reflection 失敗 → 捕捉並記錄錯誤，繼續處理其他路由
- 最大深度超過 → 停止遞迴，回傳 `type: object`

### 優雅降級 (Graceful Degradation)

當無法完整分析時：
- 缺少型別提示 → 使用 DocBlock 或回退為 `type: string`
- 無法推導陣列元素 → `type: array` (不含 items)
- DTO 過於複雜 → 使用 `additionalProperties: true`

## 效能優化 (Performance)

### 預期效能指標

| 專案規模 | 端點數量 | 首次生成 | 快取命中 |
|----------|----------|----------|----------|
| 小型 | < 50 | < 100ms | < 5ms |
| 中型 | 50-200 | < 500ms | < 10ms |
| 大型 | > 200 | < 2s | < 20ms |

### 優化措施

1. **Lazy Loading**: 僅在存取時生成
2. **部分快取**: 按路由前綴分組快取
3. **非同步生成**: 使用 Symfony Messenger（未來擴展）

## 測試策略 (Testing)

### 單元測試

- `AttributeReaderTest` - 測試 Attribute 讀取
- `TypeAnalyzerTest` - 測試型別推導
- `SchemaDescriberTest` - 測試 Schema 生成
- `ConstraintConverterTest` - 測試 Constraint 轉換

### 整合測試

- `OpenApiGeneratorTest` - 測試完整生成流程
- `CacheIntegrationTest` - 測試快取機制
- `ValidationTest` - 驗證生成的 OpenAPI 符合規範

### 功能測試

- 建立範例 Controller 包含各種 Attributes
- 驗證生成的 OpenAPI JSON 正確性
- 測試邊界情況（循環引用、深層巢狀等）

**目標覆蓋率**: > 80%

## 遷移計畫 (Migration)

### 從現有骨架升級

**步驟**:
1. 保留現有 `SwaggerGenerator` 介面
2. 重構為委派給 `OpenApiGenerator`
3. 新增 DI 註冊（向後相容）

**向後相容性**:
```php
// 舊版用法仍可運作
$generator = new SwaggerGenerator($config);
$doc = $generator->generate();

// 新版提供更多功能
$generator = $container->get(OpenApiGenerator::class);
$doc = $generator->generate();
```

## 風險與緩解 (Risks)

| 風險 | 機率 | 影響 | 緩解措施 | 狀態 |
|------|------|------|----------|------|
| Reflection 效能問題 | 中 | 中 | 多層快取 + Lazy Loading | ✅ 已規劃 |
| 型別推導不準確 | 低 | 中 | 優雅降級 + DocBlock 補充 | ✅ 已規劃 |
| DTO 循環引用導致無限迴圈 | 中 | 高 | 引用追蹤棧 + 最大深度 | ✅ 已規劃 |
| 複雜 Union Types 處理 | 低 | 低 | 使用 oneOf 表示 | ✅ 已規劃 |
| Symfony 版本更新破壞 API | 低 | 中 | 明確版本需求 + CI 測試 | ✅ 已規劃 |
| 快取失效導致過期文檔 | 中 | 低 | 開發環境短 TTL | ✅ 已規劃 |

## 開放問題 (Open Questions)

- ❓ 是否需要支援 YAML 輸出格式？（目前僅 JSON）
- ❓ 如何處理 Controller 繼承與 Trait？（目前僅分析當前類別）
- ❓ 是否需要支援 OpenAPI 3.0 向後相容？（目前僅 3.1）

---

**設計版本**: 1.0
**最後更新**: 2025-01-13
**狀態**: 待審核
