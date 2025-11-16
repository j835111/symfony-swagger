<?php

namespace SymfonySwagger\Tests\Research;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * 搜尋 DTO 範例
 */
class ExampleSearchDto
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 2)]
    public string $query;

    #[Assert\Positive]
    public int $page = 1;

    #[Assert\Range(min: 1, max: 100)]
    public int $limit = 10;

    #[Assert\Choice(['date', 'relevance', 'popularity'])]
    public string $sortBy = 'relevance';
}
