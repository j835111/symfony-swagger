<?php

namespace SymfonySwagger\Tests\Research;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * 範例 Controller 用於測試 Attributes 擷取
 *
 * 此 Controller 包含各種常見的 Symfony Attributes,
 * 用於驗證 Reflection API 能否正確讀取
 */
#[Route('/api/posts')]
class ExampleController extends AbstractController
{
    /**
     * 取得文章列表
     *
     * 支援分頁和篩選
     */
    #[Route('', name: 'posts_list', methods: ['GET'])]
    public function list(
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] int $limit = 10,
        #[MapQueryParameter] ?string $search = null,
    ): JsonResponse {
        return $this->json([
            'page' => $page,
            'limit' => $limit,
            'search' => $search,
            'items' => [],
        ]);
    }

    /**
     * 取得單一文章
     */
    #[Route('/{id}', name: 'posts_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        return $this->json([
            'id' => $id,
            'title' => 'Example Post',
            'content' => 'Content here',
        ]);
    }

    /**
     * 建立新文章
     *
     * 需要 ROLE_USER 權限
     */
    #[Route('', name: 'posts_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(
        #[MapRequestPayload] ExamplePostDto $post
    ): JsonResponse {
        return $this->json([
            'id' => 123,
            'title' => $post->title,
            'content' => $post->content,
        ], Response::HTTP_CREATED);
    }

    /**
     * 更新文章
     */
    #[Route('/{id}', name: 'posts_update', methods: ['PUT'])]
    #[IsGranted('ROLE_EDITOR')]
    public function update(
        int $id,
        #[MapRequestPayload] ExamplePostDto $post
    ): JsonResponse {
        return $this->json([
            'id' => $id,
            'title' => $post->title,
            'updated' => true,
        ]);
    }

    /**
     * 刪除文章
     */
    #[Route('/{id}', name: 'posts_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: '僅管理員可以刪除文章')]
    public function delete(int $id): Response
    {
        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * 搜尋文章(使用 DTO)
     */
    #[Route('/search', name: 'posts_search', methods: ['GET'])]
    public function search(
        #[MapQueryString] ExampleSearchDto $search
    ): JsonResponse {
        return $this->json([
            'query' => $search->query,
            'results' => [],
        ]);
    }
}
