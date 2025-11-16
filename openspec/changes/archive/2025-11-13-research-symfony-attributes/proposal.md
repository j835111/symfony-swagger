# 研究 Symfony 7.x Controller Attributes 與 API Routing 資訊擷取

## Change ID
`research-symfony-attributes`

## 動機

為了實現自動生成 Swagger/OpenAPI 文檔的功能，我們需要深入了解：

1. **Symfony 7.x Controller Attributes**：可用於 Controller 的原生 Attributes 及其包含的資訊
2. **Routing 資訊擷取**：在什麼階段、通過什麼方式可以獲取到完整的 API routing 資訊（包含 request/response 結構）

這個研究將為後續實現自動化 OpenAPI 文檔生成奠定基礎。

## 目標

### 研究目標

1. **盤點 Symfony 7.x Controller Attributes**
   - `#[Route]` - 路由定義（path, methods, name, requirements, defaults 等）
   - Request 相關：`#[MapQueryParameter]`、`#[MapRequestPayload]`、`#[MapQueryString]`
   - Response 相關：返回型別提示
   - 參數轉換：`#[ParamConverter]`（若使用 SensioFrameworkExtraBundle）
   - 安全：`#[IsGranted]`
   - Cache：`#[Cache]`
   - 其他：`#[AsController]`、自定義 Attributes

2. **研究 Routing 資訊擷取時機與方法**
   - **Compile time**：透過 Compiler Pass 分析 RouteCollection
   - **Runtime**：透過 Router Service 動態獲取
   - **Reflection API**：讀取 Controller Class/Method 的 Attributes
   - **Request/Response 型別分析**：透過 ReflectionType 獲取參數與回傳型別

3. **確定最佳實踐方案**
   - 選擇適合的資訊擷取時機點
   - 確定 Request/Response 結構的分析策略
   - 評估效能與準確性的平衡

## 範圍

### 包含

- 研究 Symfony 7.x 版本的 Controller Attributes
- 探索 Router、RouteCollection、RequestContext 等核心元件
- 測試不同階段（編譯期、執行期）的資訊擷取方法
- 研究 Controller 參數型別、回傳型別的反射分析
- 調查第三方 Bundle（如 NelmioApiDocBundle、ApiPlatform）的實作方式

### 不包含

- Symfony 6.x 或更早版本的支援
- 實際實作 OpenAPI 文檔生成功能（這將在後續 change 中進行）
- 修改現有的 Bundle 功能

## 交付成果

1. **研究報告文檔**（`docs/research/symfony-attributes.md`）
   - Symfony 7.x Controller Attributes 完整清單與說明
   - 每個 Attribute 的用途、參數、範例程式碼

2. **研究報告文檔**（`docs/research/routing-introspection.md`）
   - Routing 資訊擷取方法比較分析（Compiler Pass vs Runtime）
   - 各方法的優缺點、適用場景、程式碼範例
   - 推薦方案與理由

3. **概念驗證程式碼**（`tests/Research/`）
   - 示範不同擷取方法的測試案例
   - 可執行的程式碼範例

4. **技術決策文檔**（`design.md`）
   - 基於研究結果的架構設計建議
   - 後續實作的技術方向與藍圖

## 影響範圍

- **文檔**：新增 `docs/research/` 目錄及研究報告
- **測試**：新增 `tests/Research/` 研究用測試案例
- **設計**：為後續實作提供技術基礎

## 相依性

- 無前置相依項
- 此研究將成為後續 `implement-openapi-generation` change 的基礎

## 風險與考量

1. **Reflection 效能**：大量使用 Reflection API 可能有效能開銷，需要評估與快取策略
2. **型別推導限制**：某些動態型別可能無法透過靜態分析獲取
3. **第三方 Attributes**：使用者可能使用自定義 Attributes，需要考慮擴展性

## 時程估計

- 研究 Symfony 7.x Attributes：1-2 天
- 研究 Routing 擷取方法與實驗：1-2 天
- 撰寫文檔與範例程式碼：1 天
- **總計：3-5 天**

## 驗收標準

- [ ] 完成 Symfony 7.x Controller Attributes 清單文檔，包含至少 8 個常用 Attributes
- [ ] 完成 Routing 資訊擷取方法比較文檔，至少比較 3 種方法
- [ ] 提供至少 3 個可執行的概念驗證程式碼範例
- [ ] 基於研究結果在 `design.md` 中提出明確的技術決策建議
- [ ] 通過 `openspec validate research-symfony-attributes --strict` 驗證
