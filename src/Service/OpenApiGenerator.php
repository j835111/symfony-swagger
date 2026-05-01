<?php

declare(strict_types=1);

namespace SymfonySwagger\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use SymfonySwagger\Analyzer\DocBlockDescriptionExtractor;
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
        private readonly array $config = [],
        private readonly ?LoggerInterface $logger = null,
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
        if (null !== $this->cachedDoc) {
            return $this->cachedDoc;
        }

        // L2 快取 - Symfony Cache
        if (null !== $this->cache && ($this->config['cache']['enabled'] ?? true)) {
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

        $routes = $this->routeDescriber->describe($this->router, $this->config);
        ['paths' => $paths, 'tags' => $tags] = $this->generatePathsAndTags($routes);

        $doc = [
            'openapi' => '3.1.0',
            'info' => $this->generateInfo(),
            'servers' => $this->generateServers(),
            'paths' => $paths,
        ];

        if (!empty($tags)) {
            $doc['tags'] = $tags;
        }

        // 加入 components
        $components = [];

        $schemas = $this->schemaRegistry->getSchemas();
        if (!empty($schemas)) {
            $components['schemas'] = $schemas;
        }

        $securitySchemes = $this->generateSecuritySchemes();
        if (!empty($securitySchemes)) {
            $components['securitySchemes'] = $securitySchemes;
        }

        if (!empty($components)) {
            $doc['components'] = $components;
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
     * 生成 components/securitySchemes 區塊.
     *
     * @return array<string, mixed>
     */
    private function generateSecuritySchemes(): array
    {
        if (false === ($this->config['security']['enabled'] ?? true)) {
            return [];
        }

        $securitySchemes = $this->config['security']['security_schemes'] ?? [
            'defaultAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
            ],
        ];

        if (!\is_array($securitySchemes)) {
            return [];
        }

        $defaultScheme = $this->getDefaultSecuritySchemeName();
        if (!isset($securitySchemes[$defaultScheme])) {
            $securitySchemes[$defaultScheme] = [
                'type' => 'http',
                'scheme' => 'bearer',
            ];
        }

        return $securitySchemes;
    }

    private function getDefaultSecuritySchemeName(): string
    {
        $defaultScheme = $this->config['security']['default_scheme'] ?? 'defaultAuth';

        return \is_string($defaultScheme) && '' !== $defaultScheme ? $defaultScheme : 'defaultAuth';
    }

    /**
     * 生成 paths 與根層 tags 區塊.
     *
     * @param array<string, array<string, mixed>> $routes
     *
     * @return array{paths: array<string, mixed>, tags: list<array<string, string>>}
     */
    private function generatePathsAndTags(array $routes): array
    {
        $paths = [];
        $tags = [];

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
                    $operation = $this->operationDescriber->describe($reflection, $route, $method);
                    $paths[$path][$httpMethod] = $operation;
                    $tag = $this->generateTagDefinition($reflection->getDeclaringClass());
                    $tags[$tag['name']] = $tag;
                } catch (\Throwable $e) {
                    $this->logger?->warning('Failed to describe route "{route}" ({method} {path}): {error}', [
                        'route' => $routeName,
                        'method' => $method,
                        'path' => $path,
                        'error' => $e->getMessage(),
                        'exception' => $e,
                    ]);
                    continue;
                }
            }
        }

        return [
            'paths' => $paths,
            'tags' => array_values($tags),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function generateTagDefinition(\ReflectionClass $controllerClass): array
    {
        $name = str_replace('Controller', '', $controllerClass->getShortName());
        $tag = ['name' => $name];

        $description = DocBlockDescriptionExtractor::getSchemaDescription($controllerClass);
        if (null !== $description) {
            $tag['description'] = $description;
        }

        return $tag;
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

        if (null !== $this->cache) {
            $cacheKey = $this->getCacheKey();
            $this->cache->delete($cacheKey);
        }
    }
}
