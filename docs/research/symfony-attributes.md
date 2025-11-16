# Symfony 7.x Controller Attributes 完整研究

## 概述

本文檔研究 Symfony 7.x 中可用於 Controller 的 PHP Attributes,為自動生成 OpenAPI 文檔提供技術基礎。

## 核心 Routing Attributes

### 1. `#[Route]` - 路由定義

**命名空間**: `Symfony\Component\Routing\Attribute\Route`

**用途**: 定義 HTTP 路由,將 URL 對應到 Controller 方法

**參數詳解**:

| 參數 | 類型 | 說明 | 範例 |
|------|------|------|------|
| `path` | `string` | URL 路徑模式,支援參數 `{name}` | `'/blog/{slug}'` |
| `name` | `string` | 路由名稱,用於 URL 生成 | `'blog_show'` |
| `methods` | `array` | 允許的 HTTP 方法 | `['GET', 'POST']` |
| `requirements` | `array` | 參數驗證規則(正規表達式) | `['id' => '\d+']` |
| `defaults` | `array` | 參數預設值 | `['page' => 1]` |
| `condition` | `string` | 路由匹配條件(Expression Language) | `"params['id'] < 1000"` |
| `priority` | `int` | 路由優先順序(數字越大越優先) | `10` |
| `locale` | `string` | 預設語言設定 | `'zh_TW'` |
| `format` | `string` | 預設回應格式 | `'json'` |
| `alias` | `array` | 路由別名(Symfony 7.3+) | `['old_blog_show']` |
| `env` | `string` | 環境限制 | `'dev'` |

**基本使用範例**:

```php
<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BlogController extends AbstractController
{
    // 基本路由
    #[Route('/blog', name: 'blog_list', methods: ['GET'])]
    public function list(): Response
    {
        // ...
    }

    // 帶參數的路由
    #[Route(
        path: '/blog/{slug}',
        name: 'blog_show',
        requirements: ['slug' => '[a-z0-9\-]+'],
        methods: ['GET']
    )]
    public function show(string $slug): Response
    {
        // ...
    }

    // 帶預設值的路由
    #[Route(
        '/blog/page/{page}',
        name: 'blog_page',
        requirements: ['page' => '\d+'],
        defaults: ['page' => 1]
    )]
    public function page(int $page = 1): Response
    {
        // ...
    }

    // 使用條件表達式
    #[Route(
        '/blog/{id}',
        name: 'blog_show_id',
        condition: "params['id'] < 1000",
        requirements: ['id' => '\d+']
    )]
    public function showById(int $id): Response
    {
        // 僅當 id < 1000 時匹配此路由
    }

    // 參數自動轉換(Symfony 6.1+)
    #[Route('/post/{slug:post}', name: 'post_show')]
    public function showPost(Post $post): Response
    {
        // $post 自動從 slug 查找並注入
    }
}
```

**類別層級路由前綴**:

```php
#[Route('/api/v1')]
class ApiController extends AbstractController
{
    #[Route('/users', name: 'api_users_list')]  // 實際路徑: /api/v1/users
    public function listUsers(): Response { }

    #[Route('/users/{id}', name: 'api_users_show')]  // 實際路徑: /api/v1/users/{id}
    public function showUser(int $id): Response { }
}
```

**OpenAPI 關聯性**:
- `path` → OpenAPI `path`
- `methods` → OpenAPI `operation` (get, post, put, delete 等)
- `requirements` → OpenAPI `parameters[].schema.pattern`
- `defaults` → OpenAPI `parameters[].schema.default`

---

## Request Mapping Attributes (Symfony 6.3+)

### 2. `#[MapQueryParameter]` - 查詢參數對應

**命名空間**: `Symfony\Component\HttpKernel\Attribute\MapQueryParameter`

**用途**: 將單一 URL 查詢參數自動對應到 Controller 參數,並進行型別轉換與驗證

**參數詳解**:

| 參數 | 類型 | 說明 | 預設值 |
|------|------|------|--------|
| `name` | `string` | 查詢參數名稱(若為 null 則使用參數名) | `null` |
| `filter` | `int` | PHP filter 常數(如 `FILTER_VALIDATE_REGEXP`) | `null` |
| `options` | `array` | Filter 選項 | `[]` |

**支援的參數型別**:
- 基本型別: `string`, `int`, `float`, `bool`
- 陣列: `array`
- 列舉: `\BackedEnum`
- UID: `AbstractUid` (Symfony UID component)

**使用範例**:

```php
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends AbstractController
{
    // 基本用法
    #[Route('/products')]
    public function list(
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] int $limit = 10,
        #[MapQueryParameter] ?string $category = null,
    ): Response {
        // GET /products?page=2&limit=20&category=electronics
        // $page = 2, $limit = 20, $category = 'electronics'
    }

    // 使用 filter 驗證
    #[Route('/search')]
    public function search(
        #[MapQueryParameter(
            filter: \FILTER_VALIDATE_REGEXP,
            options: ['regexp' => '/^[a-z0-9]+$/i']
        )]
        string $query
    ): Response {
        // query 參數必須符合正規表達式
    }

    // 陣列參數
    #[Route('/filter')]
    public function filter(
        #[MapQueryParameter] array $tags = [],
        #[MapQueryParameter] array $colors = []
    ): Response {
        // GET /filter?tags[]=php&tags[]=symfony&colors[]=red
        // $tags = ['php', 'symfony'], $colors = ['red']
    }

    // 布林參數
    #[Route('/products')]
    public function products(
        #[MapQueryParameter] bool $inStock = false
    ): Response {
        // GET /products?inStock=1  → $inStock = true
        // GET /products?inStock=true  → $inStock = true
    }
}
```

**錯誤處理**:
- 驗證失敗 → HTTP 422 (Unprocessable Entity)
- 資料格式錯誤 → HTTP 400 (Bad Request)

**OpenAPI 關聯性**:
- 對應到 `parameters[in=query]`
- 型別自動對應到 `schema.type`
- Required/Optional 對應到 `required` 屬性

---

### 3. `#[MapQueryString]` - 整體查詢字串對應

**命名空間**: `Symfony\Component\HttpKernel\Attribute\MapQueryString`

**用途**: 將整個查詢字串對應到 DTO 物件,支援驗證

**參數詳解**:

| 參數 | 類型 | 說明 | 預設值 |
|------|------|------|--------|
| `validationGroups` | `array\|string\|null` | 驗證群組 | `null` |
| `validationFailedStatusCode` | `int` | 驗證失敗的 HTTP 狀態碼 | `422` |
| `serializationContext` | `array` | 序列化上下文 | `[]` |
| `resolver` | `string` | 自訂 Resolver 類別 | `null` |

**使用範例**:

```php
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Validator\Constraints as Assert;

// DTO 定義
class ProductFilterDto
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 50)]
    public string $search = '';

    #[Assert\Positive]
    #[Assert\LessThanOrEqual(100)]
    public int $limit = 10;

    #[Assert\PositiveOrZero]
    public int $offset = 0;

    #[Assert\Choice(['name', 'price', 'date'])]
    public string $sortBy = 'name';

    #[Assert\Choice(['asc', 'desc'])]
    public string $order = 'asc';

    public array $categories = [];
}

// Controller 使用
class ProductController extends AbstractController
{
    #[Route('/products')]
    public function list(
        #[MapQueryString(
            validationGroups: ['search'],
            validationFailedStatusCode: Response::HTTP_BAD_REQUEST
        )]
        ProductFilterDto $filters
    ): Response {
        // GET /products?search=laptop&limit=20&sortBy=price&order=desc&categories[]=1&categories[]=2
        // $filters 物件會自動填充並驗證

        // 使用 DTO
        $search = $filters->search;
        $limit = $filters->limit;
        // ...
    }

    // 使用預設值
    #[Route('/search')]
    public function search(
        #[MapQueryString]
        ProductFilterDto $filters = new ProductFilterDto()
    ): Response {
        // 若無查詢參數,使用 DTO 的預設值
    }
}
```

**搭配 Symfony 7.3+ nested arrays**:

```php
class FilterDto
{
    #[MapQueryString(key: 'price')]
    public PriceRangeDto $priceRange;
}

class PriceRangeDto
{
    public int $min = 0;
    public int $max = 9999;
}

// GET /products?price[min]=100&price[max]=500
```

**OpenAPI 關聯性**:
- 多個 `parameters[in=query]` 組合
- DTO 屬性對應到各個 query parameter
- 驗證規則對應到 `schema` 定義

---

### 4. `#[MapRequestPayload]` - 請求主體對應

**命名空間**: `Symfony\Component\HttpKernel\Attribute\MapRequestPayload`

**用途**: 將 JSON/Form 請求主體反序列化為 DTO 物件

**參數詳解**:

| 參數 | 類型 | 說明 | 預設值 |
|------|------|------|--------|
| `acceptFormat` | `string\|string[]\|null` | 接受的格式 (json, xml, form 等) | `null` (全部) |
| `validationGroups` | `array\|string\|null` | 驗證群組 | `null` |
| `validationFailedStatusCode` | `int` | 驗證失敗狀態碼 | `422` |
| `serializationContext` | `array` | 反序列化上下文 | `[]` |
| `resolver` | `string` | 自訂 Resolver | `null` |
| `type` | `string` | 陣列元素型別 (Symfony 7.1+) | `null` |

**使用範例**:

```php
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Validator\Constraints as Assert;

// DTO 定義
class CreateProductDto
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 100)]
    public string $name;

    #[Assert\NotBlank]
    #[Assert\Length(max: 1000)]
    public string $description;

    #[Assert\Positive]
    public float $price;

    #[Assert\PositiveOrZero]
    public int $stock = 0;

    public array $tags = [];
}

// Controller 使用
class ProductController extends AbstractController
{
    // 建立商品
    #[Route('/products', methods: ['POST'])]
    public function create(
        #[MapRequestPayload(
            acceptFormat: 'json',
            validationGroups: ['create']
        )]
        CreateProductDto $product
    ): Response {
        // POST /products
        // Content-Type: application/json
        // Body: {"name":"Laptop","description":"...","price":999.99,"stock":10,"tags":["electronics"]}

        // $product 已自動反序列化並驗證
        return $this->json([
            'id' => 123,
            'name' => $product->name,
            'price' => $product->price
        ], Response::HTTP_CREATED);
    }

    // 更新商品
    #[Route('/products/{id}', methods: ['PUT'])]
    public function update(
        int $id,
        #[MapRequestPayload]
        CreateProductDto $product
    ): Response {
        // PUT /products/123
        // $product 包含更新資料
    }

    // 批次建立 (Symfony 7.1+)
    #[Route('/products/batch', methods: ['POST'])]
    public function batchCreate(
        #[MapRequestPayload(type: CreateProductDto::class)]
        array $products
    ): Response {
        // POST /products/batch
        // Body: [{"name":"..."},{"name":"..."}]
        // $products 是 CreateProductDto[] 陣列
    }
}
```

**與 Serializer Groups 搭配**:

```php
use Symfony\Component\Serializer\Attribute\Groups;

class UserDto
{
    #[Groups(['user:write'])]
    public string $username;

    #[Groups(['user:write'])]
    public string $email;

    #[Groups(['admin:write'])]  // 僅 admin 群組可寫入
    public ?string $role = null;
}

#[Route('/users', methods: ['POST'])]
public function createUser(
    #[MapRequestPayload(
        serializationContext: ['groups' => ['user:write']]
    )]
    UserDto $user
): Response {
    // $user->role 不會從 JSON 填充(除非指定 admin:write 群組)
}
```

**錯誤處理**:
- 格式不支援 → HTTP 415 (Unsupported Media Type)
- JSON 解析失敗 → HTTP 400 (Bad Request)
- 驗證失敗 → HTTP 422 (Unprocessable Entity)

**OpenAPI 關聯性**:
- 對應到 `requestBody.content`
- `acceptFormat` 對應到 `requestBody.content.<mediaType>`
- DTO 結構對應到 `schema` 定義

---

### 5. `#[MapUploadedFile]` - 檔案上傳對應

**命名空間**: `Symfony\Component\HttpKernel\Attribute\MapUploadedFile`

**用途**: 處理檔案上傳,支援驗證

**參數詳解**:

| 參數 | 類型 | 說明 |
|------|------|------|
| `constraints` | `Constraint[]` | 檔案驗證規則 |
| `name` | `string\|null` | 表單欄位名稱 (Symfony 7.1+) |
| `validationFailedStatusCode` | `int` | 驗證失敗狀態碼 (Symfony 7.1+) |

**使用範例**:

```php
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

class FileController extends AbstractController
{
    // 單一檔案上傳
    #[Route('/upload/avatar', methods: ['POST'])]
    public function uploadAvatar(
        #[MapUploadedFile([
            new Assert\File(
                maxSize: '2M',
                mimeTypes: ['image/jpeg', 'image/png', 'image/gif']
            ),
            new Assert\Image(
                maxWidth: 2000,
                maxHeight: 2000
            )
        ])]
        UploadedFile $avatar
    ): Response {
        // 檔案已驗證,可直接使用
        $filename = $avatar->getClientOriginalName();
        $avatar->move($this->getParameter('upload_directory'), $filename);

        return $this->json(['filename' => $filename]);
    }

    // 可選檔案
    #[Route('/upload/logo', methods: ['POST'])]
    public function uploadLogo(
        #[MapUploadedFile([
            new Assert\File(mimeTypes: ['image/png'])
        ])]
        ?UploadedFile $logo = null
    ): Response {
        if ($logo) {
            // 處理上傳
        }
        return $this->json(['uploaded' => $logo !== null]);
    }

    // 多檔案上傳
    #[Route('/upload/documents', methods: ['POST'])]
    public function uploadDocuments(
        #[MapUploadedFile([
            new Assert\File(
                maxSize: '10M',
                mimeTypes: ['application/pdf', 'application/msword']
            )
        ])]
        array $documents
    ): Response {
        // $documents 是 UploadedFile[] 陣列
        $uploadedFiles = [];
        foreach ($documents as $doc) {
            $filename = uniqid() . '.' . $doc->guessExtension();
            $doc->move($this->getParameter('upload_directory'), $filename);
            $uploadedFiles[] = $filename;
        }

        return $this->json(['files' => $uploadedFiles]);
    }

    // 自訂欄位名稱 (Symfony 7.1+)
    #[Route('/profile/photo', methods: ['POST'])]
    public function updatePhoto(
        #[MapUploadedFile(
            name: 'profile_picture',
            constraints: [new Assert\Image(maxSize: '5M')],
            validationFailedStatusCode: 400
        )]
        UploadedFile $photo
    ): Response {
        // 表單欄位名稱為 profile_picture,但參數名稱為 $photo
    }
}
```

**HTML 表單範例**:

```html
<!-- 單一檔案 -->
<form method="POST" enctype="multipart/form-data">
    <input type="file" name="avatar">
    <button type="submit">Upload</button>
</form>

<!-- 多檔案 -->
<form method="POST" enctype="multipart/form-data">
    <input type="file" name="documents[]" multiple>
    <button type="submit">Upload</button>
</form>
```

**OpenAPI 關聯性**:
- `requestBody.content['multipart/form-data']`
- Schema 型別: `string` with `format: binary`

---

## 安全性 Attributes

### 6. `#[IsGranted]` - 存取控制

**命名空間**: `Symfony\Component\Security\Http\Attribute\IsGranted`

**用途**: 檢查使用者權限,限制 Controller 存取

**參數詳解**:

| 參數 | 類型 | 說明 | 預設值 |
|------|------|------|--------|
| `attribute` | `string\|Expression` | 權限名稱或表達式 | - |
| `subject` | `mixed` | 權限檢查的對象 | `null` |
| `message` | `string` | 拒絕存取的訊息 | - |
| `statusCode` | `int` | HTTP 狀態碼 | `403` |
| `exceptionCode` | `int` | 例外代碼 | `0` |
| `methods` | `string[]\|string\|null` | HTTP 方法限制 (Symfony 7.4+) | `null` |

**使用範例**:

```php
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\ExpressionLanguage\Expression;

// 類別層級 - 所有方法都需要權限
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    // 所有方法都需要 ROLE_ADMIN
}

// 方法層級
class PostController extends AbstractController
{
    // 基本用法
    #[Route('/posts/new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(): Response { }

    // 自訂訊息和狀態碼
    #[Route('/posts/{id}/delete', methods: ['DELETE'])]
    #[IsGranted(
        'ROLE_ADMIN',
        message: '您沒有權限刪除文章',
        statusCode: 403,
        exceptionCode: 10001
    )]
    public function delete(int $id): Response { }

    // 檢查物件權限
    #[Route('/posts/{id}/edit')]
    #[IsGranted('POST_EDIT', subject: 'post')]
    public function edit(Post $post): Response {
        // 檢查使用者是否有編輯 $post 的權限
    }

    // 使用表達式
    #[Route('/posts/{id}/publish')]
    #[IsGranted(new Expression(
        "is_granted('ROLE_ADMIN') or (is_granted('ROLE_EDITOR') and user.getDepartment() == 'news')"
    ))]
    public function publish(int $id): Response { }

    // 限制特定 HTTP 方法 (Symfony 7.4+)
    #[Route('/posts/{id}', methods: ['GET', 'POST', 'PUT'])]
    #[IsGranted('ROLE_USER', methods: ['GET'])]  // 僅 GET 需要登入
    #[IsGranted('ROLE_EDITOR', methods: ['POST', 'PUT'])]  // POST/PUT 需要編輯權
    public function manage(int $id): Response { }

    // 多重權限檢查(全部都要通過)
    #[Route('/admin/settings')]
    #[IsGranted('ROLE_ADMIN')]
    #[IsGranted('SETTING_MANAGE')]
    public function settings(): Response { }
}
```

**與 Voter 搭配使用**:

```php
// Voter 定義
class PostVoter extends Voter
{
    const EDIT = 'POST_EDIT';
    const DELETE = 'POST_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::DELETE]) && $subject instanceof Post;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        return match($attribute) {
            self::EDIT => $subject->getAuthor() === $user || in_array('ROLE_ADMIN', $user->getRoles()),
            self::DELETE => in_array('ROLE_ADMIN', $user->getRoles()),
        };
    }
}

// Controller 使用
#[Route('/posts/{id}/edit')]
#[IsGranted('POST_EDIT', subject: 'post')]
public function edit(Post $post): Response
{
    // 只有作者或管理員可以編輯
}
```

**OpenAPI 關聯性**:
- 對應到 `security` 定義
- 可標註需要的認證方式和權限範圍

---

### 7. `#[CurrentUser]` - 當前使用者注入

**命名空間**: `Symfony\Bundle\SecurityBundle\Attribute\CurrentUser`

**用途**: 自動注入當前登入的使用者物件

**使用範例**:

```php
use Symfony\Bundle\SecurityBundle\Attribute\CurrentUser;
use App\Entity\User;

class ProfileController extends AbstractController
{
    // 基本用法
    #[Route('/profile')]
    public function show(
        #[CurrentUser] User $user
    ): Response {
        // $user 自動注入當前登入使用者
        return $this->render('profile/show.html.twig', [
            'user' => $user
        ]);
    }

    // 可選(未登入時為 null)
    #[Route('/welcome')]
    public function welcome(
        #[CurrentUser] ?User $user = null
    ): Response {
        if ($user) {
            return $this->render('welcome_user.html.twig', ['user' => $user]);
        }
        return $this->render('welcome_guest.html.twig');
    }

    // Union Types (Symfony 7.4+)
    #[Route('/dashboard')]
    public function dashboard(
        #[CurrentUser] User|AdminUser $user
    ): Response {
        // 支援多種使用者型別
    }
}
```

**OpenAPI 關聯性**:
- 不直接對應到 OpenAPI
- 但暗示需要認證(`security` 定義)

---

## 效能與快取 Attributes

### 8. `#[Cache]` - HTTP 快取

**命名空間**: `Symfony\Component\HttpKernel\Attribute\Cache`

**用途**: 設定 HTTP 快取 headers

**參數詳解**:

| 參數 | 類型 | 說明 |
|------|------|------|
| `expires` | `string\|int` | 過期時間 |
| `maxage` | `int` | 最大快取時間(秒) |
| `smaxage` | `int` | 共享快取最大時間 |
| `public` | `bool` | 公開快取 |
| `private` | `bool` | 私有快取 |
| `mustRevalidate` | `bool` | 必須重新驗證 |
| `vary` | `array` | Vary headers |
| `lastModified` | `string` | 最後修改時間 |
| `etag` | `string` | ETag 值 |

**使用範例**:

```php
use Symfony\Component\HttpKernel\Attribute\Cache;

class BlogController extends AbstractController
{
    // 基本快取
    #[Route('/blog')]
    #[Cache(maxage: 3600, public: true)]
    public function list(): Response {
        // 快取 1 小時
    }

    // 必須重新驗證
    #[Route('/blog/{slug}')]
    #[Cache(
        maxage: 600,
        public: true,
        mustRevalidate: true
    )]
    public function show(string $slug): Response {
        // 快取 10 分鐘,過期後必須重新驗證
    }

    // Vary by headers
    #[Route('/api/posts')]
    #[Cache(
        maxage: 3600,
        public: true,
        vary: ['Accept', 'Accept-Language']
    )]
    public function api(): Response {
        // 根據 Accept 和 Accept-Language 區分快取
    }

    // 私有快取(使用者專屬)
    #[Route('/profile')]
    #[Cache(maxage: 300, private: true)]
    public function profile(#[CurrentUser] User $user): Response {
        // 僅使用者瀏覽器快取 5 分鐘
    }
}
```

**OpenAPI 關聯性**:
- 不直接對應,但可在文檔中註記
- 可用於說明 API 的快取策略

---

## 序列化 Attributes

### 9. `#[Groups]` - 序列化群組

**命名空間**: `Symfony\Component\Serializer\Attribute\Groups`

**用途**: 控制序列化/反序列化時包含的屬性

**使用範例**:

```php
use Symfony\Component\Serializer\Attribute\Groups;

class User
{
    #[Groups(['user:read', 'user:write'])]
    public int $id;

    #[Groups(['user:read', 'user:write', 'public'])]
    public string $username;

    #[Groups(['user:write'])]  // 僅寫入時使用
    public string $password;

    #[Groups(['user:read'])]  // 僅讀取時返回
    public string $email;

    #[Groups(['user:read', 'admin:read'])]
    public array $roles;

    #[Groups(['admin:read'])]  // 僅管理員可見
    public bool $isActive;
}

// Controller 使用
#[Route('/api/users/{id}', methods: ['GET'])]
public function show(User $user, SerializerInterface $serializer): Response
{
    // 僅返回 user:read 群組的屬性
    $json = $serializer->serialize($user, 'json', [
        'groups' => ['user:read']
    ]);

    return new Response($json, 200, ['Content-Type' => 'application/json']);
}

// 與 MapRequestPayload 搭配
#[Route('/api/users', methods: ['POST'])]
public function create(
    #[MapRequestPayload(serializationContext: ['groups' => ['user:write']])]
    User $user
): Response {
    // 僅接受 user:write 群組的屬性
}
```

**OpenAPI 關聯性**:
- 影響 `schema` 定義的屬性
- 不同群組可能對應不同的 DTO schema

---

### 10. `#[Context]` - 序列化上下文

**命名空間**: `Symfony\Component\Serializer\Attribute\Context`

**用途**: 設定序列化/反序列化的上下文選項

**使用範例**:

```php
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

class Article
{
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    public \DateTimeInterface $publishedAt;

    #[Context([
        DateTimeNormalizer::FORMAT_KEY => \DateTime::RFC3339,
        DateTimeNormalizer::TIMEZONE_KEY => 'UTC'
    ])]
    public \DateTimeInterface $createdAt;
}
```

**OpenAPI 關聯性**:
- 影響 `schema` 的 `format` 定義
- 特別是日期時間格式

---

## 其他常用 Attributes

### 11. `#[AsController]` - 標記 Controller

**命名空間**: `Symfony\Bundle\FrameworkBundle\Controller\Attribute\AsController`

**用途**: 明確標記類別為 Controller(通常自動偵測)

**使用範例**:

```php
use Symfony\Bundle\FrameworkBundle\Controller\Attribute\AsController;

#[AsController]
class CustomController
{
    // Symfony 會將此類別視為 Controller
    // 自動啟用 argument resolver 等功能
}
```

---

### 12. `#[MapDateTime]` - 日期時間對應

**命名空間**: `Symfony\Component\HttpKernel\Attribute\MapDateTime`

**用途**: 將字串參數解析為 DateTime 物件

**使用範例**:

```php
use Symfony\Component\HttpKernel\Attribute\MapDateTime;

class EventController extends AbstractController
{
    #[Route('/events/range')]
    public function range(
        #[MapDateTime(format: 'Y-m-d')] \DateTimeInterface $start,
        #[MapDateTime(format: 'Y-m-d')] \DateTimeInterface $end
    ): Response {
        // GET /events/range?start=2025-01-01&end=2025-12-31
        // $start 和 $end 自動解析為 DateTime 物件
    }

    #[Route('/events/{date}')]
    public function byDate(
        #[MapDateTime(format: 'Y-m-d')] \DateTimeInterface $date
    ): Response {
        // GET /events/2025-01-15
    }
}
```

---

## 第三方 Attributes (SensioFrameworkExtraBundle - 已淘汰)

**注意**: Symfony 6.2+ 已將 SensioFrameworkExtraBundle 的功能內建,建議使用原生 Attributes。

### 舊版 Attributes 對應表

| 舊版 (SensioFrameworkExtraBundle) | 新版 (Symfony 原生) |
|-----------------------------------|---------------------|
| `@Route` | `#[Route]` |
| `@ParamConverter` | `#[MapEntity]` |
| `@Security` / `@IsGranted` | `#[IsGranted]` |
| `@Cache` | `#[Cache]` |
| `@Template` | `#[Template]` (保留) |

---

## OpenAPI 文檔生成相關性總結

### 高關聯性(必須處理)

1. **`#[Route]`** - 定義 API 端點和 HTTP 方法
2. **`#[MapQueryParameter]`** - 定義 query parameters
3. **`#[MapQueryString]`** - 定義複雜 query parameters (DTO)
4. **`#[MapRequestPayload]`** - 定義 request body schema
5. **`#[MapUploadedFile]`** - 定義檔案上傳 schema

### 中關聯性(應該處理)

6. **`#[IsGranted]`** - 標註安全性需求
7. **`#[Groups]`** - 影響 schema 屬性
8. **`#[Cache]`** - 標註快取資訊
9. **型別提示(Return Type)** - 定義 response schema

### 低關聯性(可選處理)

10. **`#[CurrentUser]`** - 暗示需要認證
11. **`#[Context]`** - 影響格式化
12. **`#[MapDateTime]`** - 日期格式說明

---

## 型別對應到 OpenAPI Schema

### PHP 型別 → OpenAPI 型別對應表

| PHP 型別 | OpenAPI type | OpenAPI format | 備註 |
|----------|--------------|----------------|------|
| `string` | `string` | - | |
| `int` | `integer` | `int32` | |
| `float` | `number` | `float` | |
| `bool` | `boolean` | - | |
| `array` | `array` | - | 需分析元素型別 |
| `object` | `object` | - | 需遞迴分析屬性 |
| `\DateTimeInterface` | `string` | `date-time` | ISO 8601 |
| `\DateTimeImmutable` | `string` | `date-time` | |
| `?Type` | `Type + nullable: true` | - | PHP 8 Nullable |
| `Type1\|Type2` | `oneOf` / `anyOf` | - | PHP 8 Union Types |
| `BackedEnum` | `string` / `integer` | - | + `enum` 值列表 |
| `UploadedFile` | `string` | `binary` | multipart/form-data |

---

## Attributes 讀取範例

```php
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Routing\Attribute\Route;

class AttributeReader
{
    public function readControllerAttributes(string $controllerClass): array
    {
        $reflectionClass = new ReflectionClass($controllerClass);
        $data = [];

        // 讀取類別層級的 Attributes
        foreach ($reflectionClass->getAttributes() as $attribute) {
            $data['class'][] = [
                'name' => $attribute->getName(),
                'arguments' => $attribute->getArguments(),
                'instance' => $attribute->newInstance(),
            ];
        }

        // 讀取方法層級的 Attributes
        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $controllerClass) {
                continue;  // 跳過繼承的方法
            }

            $methodData = [
                'name' => $method->getName(),
                'attributes' => [],
                'parameters' => [],
                'returnType' => $method->getReturnType()?->getName(),
            ];

            // 讀取方法的 Attributes
            foreach ($method->getAttributes() as $attribute) {
                $methodData['attributes'][] = [
                    'name' => $attribute->getName(),
                    'arguments' => $attribute->getArguments(),
                    'instance' => $attribute->newInstance(),
                ];
            }

            // 讀取參數的 Attributes
            foreach ($method->getParameters() as $param) {
                $paramData = [
                    'name' => $param->getName(),
                    'type' => $param->getType()?->getName(),
                    'isOptional' => $param->isOptional(),
                    'defaultValue' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
                    'attributes' => [],
                ];

                foreach ($param->getAttributes() as $attribute) {
                    $paramData['attributes'][] = [
                        'name' => $attribute->getName(),
                        'arguments' => $attribute->getArguments(),
                        'instance' => $attribute->newInstance(),
                    ];
                }

                $methodData['parameters'][] = $paramData;
            }

            $data['methods'][] = $methodData;
        }

        return $data;
    }
}

// 使用範例
$reader = new AttributeReader();
$attributes = $reader->readControllerAttributes(BlogController::class);

// 尋找所有 Route attributes
foreach ($attributes['methods'] as $method) {
    foreach ($method['attributes'] as $attr) {
        if ($attr['instance'] instanceof Route) {
            $route = $attr['instance'];
            echo "Path: {$route->getPath()}\n";
            echo "Methods: " . implode(', ', $route->getMethods()) . "\n";
        }
    }
}
```

---

## 總結

本研究涵蓋了 Symfony 7.x 中 **12+ 個常用 Controller Attributes**:

### 核心 Attributes (必須支援)
1. ✅ `#[Route]` - 路由定義
2. ✅ `#[MapQueryParameter]` - Query 參數
3. ✅ `#[MapQueryString]` - Query DTO
4. ✅ `#[MapRequestPayload]` - Request Body
5. ✅ `#[MapUploadedFile]` - 檔案上傳

### 擴展 Attributes (建議支援)
6. ✅ `#[IsGranted]` - 權限控制
7. ✅ `#[CurrentUser]` - 使用者注入
8. ✅ `#[Cache]` - HTTP 快取
9. ✅ `#[Groups]` - 序列化群組
10. ✅ `#[Context]` - 序列化上下文

### 輔助 Attributes (可選支援)
11. ✅ `#[AsController]` - Controller 標記
12. ✅ `#[MapDateTime]` - 日期時間對應

所有 Attributes 都可透過 **Reflection API** 讀取,為後續自動生成 OpenAPI 文檔提供完整的技術基礎。

---

## 參考資源

- [Symfony Attributes Overview](https://symfony.com/doc/current/reference/attributes.html)
- [Symfony Routing](https://symfony.com/doc/current/routing.html)
- [Symfony Controller](https://symfony.com/doc/current/controller.html)
- [Symfony Serializer](https://symfony.com/doc/current/serializer.html)
- [PHP 8 Attributes RFC](https://wiki.php.net/rfc/attributes_v2)
