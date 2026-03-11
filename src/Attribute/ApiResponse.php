<?php

declare(strict_types=1);

namespace SymfonySwagger\Attribute;

/**
 * ApiResponse - 標注 API 回應型別.
 *
 * 一般 JSON 信封格式（預設）：
 *   { code: int, message: string, data: <DTO> }
 *
 * 使用範例（單筆 DTO）：
 * #[ApiResponse(type: UserResponse::class)]
 *
 * 使用範例（DTO 陣列）：
 * #[ApiResponse(type: UserResponse::class, collection: true)]
 *
 * 使用範例（純訊息，無 data）：
 * #[ApiResponse]
 *
 * 使用範例（檔案下載，預設 application/octet-stream）：
 * #[ApiResponse(file: true)]
 *
 * 使用範例（指定 MIME 類型）：
 * #[ApiResponse(file: true, fileMediaType: 'application/pdf')]
 * #[ApiResponse(file: true, fileMediaType: 'text/csv')]
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class ApiResponse
{
    /**
     * @param class-string|null $type          回應 data 欄位的 DTO class 名稱；null 表示 data 為空（僅 JSON 模式有效）
     * @param bool              $collection    是否回傳 DTO 陣列（true = array of DTO；false = 單筆 DTO）
     * @param bool              $file          是否為檔案下載回應；true 時跳過 JSON 信封，改用 binary schema
     * @param string            $fileMediaType 檔案 MIME 類型，預設 application/octet-stream
     */
    public function __construct(
        public readonly ?string $type = null,
        public readonly bool $collection = false,
        public readonly bool $file = false,
        public readonly string $fileMediaType = 'application/octet-stream',
    ) {
    }
}
