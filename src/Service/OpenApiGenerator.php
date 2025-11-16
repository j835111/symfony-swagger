<?php

declare(strict_types=1);

namespace SymfonySwagger\Service;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use SymfonySwagger\Service\Describer\OperationDescriber;
use SymfonySwagger\Service\Describer\RouteDescriber;
use SymfonySwagger\Service\Registry\SchemaRegistry;

/**
 * OpenApiGenerator - OpenAPI 文檔生成主服務.
 *
 * 負責協調所有 Describer,生成完整的 OpenAPI 3.1 文檔。
 */
class OpenApiGenerator
{
    /** @var array<string, mixed>|null L1 快取 (Request level) */
    private ?array $cachedDoc = null;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly RouterInterface $router,
        private readonly RouteDescriber $routeDescriber,
        private readonly OperationDescriber $operationDescriber,
        private readonly SchemaRegistry $schemaRegistry,
        private readonly ?CacheInterface $cache = null,
        private readonly array $config = []
    ) {
    }

    /**
     * 生成 OpenAPI 文檔.
     *
     * @return array<string, mixed>
     */
    public function generate(): array
    {
        // L1 快取 - Request level
        if ($this->cachedDoc !== null) {
            return $this->cachedDoc;
        }

        // L2 快取 - Symfony Cache
        if ($this->cache !== null && ($this->config['cache']['enabled'] ?? true)) {
            $cacheKey = $this->getCacheKey();
            $ttl = $this->config['cache']['ttl'] ?? 3600;

            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($ttl) {
                $item->expiresAfter($ttl);
                return $this->doGenerate();
            });
        }

        return $this->doGenerate();
    }

    /**
     * 實際執行生成邏輯.
     *
     * @return array<string, mixed>
     */
    private function doGenerate(): array
    {
        // 清空 Schema Registry
        $this->schemaRegistry->clear();

        $doc = [
            'openapi' => '3.1.0',
            'info' => $this->generateInfo(),
            'servers' => $this->generateServers(),
            'paths' => $this->generatePaths(),
        ];

        // 加入 components/schemas
        $schemas = $this->schemaRegistry->getSchemas();
        if (!empty($schemas)) {
            $doc['components'] = [
                'schemas' => $schemas,
            ];
        }

        // 儲存到 L1 快取
        $this->cachedDoc = $doc;

        return $doc;
    }

    /**
     * 生成 info 區塊.
     *
     * @return array<string, mixed>
     */
    private function generateInfo(): array
    {
        return [
            'title' => $this->config['info']['title'] ?? 'API Documentation',
            'description' => $this->config['info']['description'] ?? '',
            'version' => $this->config['info']['version'] ?? '1.0.0',
        ];
    }

    /**
     * 生成 servers 區塊.
     *
     * @return list<array<string, string>>
     */
    private function generateServers(): array
    {
        return $this->config['servers'] ?? [];
    }

    /**
     * 生成 paths 區塊.
     *
     * @return array<string, mixed>
     */
    private function generatePaths(): array
    {
        $paths = [];

        // 取得所有路由
        $routes = $this->routeDescriber->describe($this->router, $this->config);

        foreach ($routes as $routeName => $routeInfo) {
            $route = $routeInfo['route'];
            $reflection = $routeInfo['reflection'];

            $path = $route->getPath();
            $methods = $route->getMethods();

            // 如果沒有指定 methods,預設為 GET
            if (empty($methods)) {
                $methods = ['GET'];
            }

            foreach ($methods as $method) {
                $httpMethod = strtolower($method);

                // 生成 Operation
                try {
                    $operation = $this->operationDescriber->describe($reflection, $route);
                    $paths[$path][$httpMethod] = $operation;
                } catch (\Throwable $e) {
                    // 忽略錯誤,繼續處理其他路由
                    continue;
                }
            }
        }

        return $paths;
    }

    /**
     * 生成快取鍵.
     */
    private function getCacheKey(): string
    {
        $configHash = md5(json_encode($this->config));
        return "openapi_doc_{$configHash}";
    }

    /**
     * 清除快取.
     */
    public function clearCache(): void
    {
        $this->cachedDoc = null;

        if ($this->cache !== null) {
            $cacheKey = $this->getCacheKey();
            $this->cache->delete($cacheKey);
        }
    }
}
