<?php

namespace SymfonySwagger\Tests\Research;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * 文章 DTO 範例
 *
 * 用於測試 DTO 類別屬性分析
 */
class ExamplePostDto
{
    #[Assert\NotBlank(message: '標題不可為空')]
    #[Assert\Length(min: 3, max: 200, minMessage: '標題至少 3 個字元')]
    public string $title;

    #[Assert\NotBlank]
    #[Assert\Length(min: 10, max: 5000)]
    public string $content;

    #[Assert\Choice(['draft', 'published', 'archived'])]
    public string $status = 'draft';

    public array $tags = [];

    public ?\DateTimeInterface $publishedAt = null;
}
