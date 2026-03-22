<?php

declare(strict_types=1);

namespace SymfonySwagger\Service\Describer;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * RouteDescriber - 路由資訊描述器.
 *
 * 負責從 Symfony Router 擷取路由資訊並提取 Controller 方法。
 */
class RouteDescriber
{
    /**
     * 描述所有路由.
     *
     * @param RouterInterface $router Symfony Router
     * @param array<string, mixed> $config 設定選項
     *
     * @return array<string, array<string, mixed>> 路由描述資訊
     */
    public function describe(RouterInterface $router, array $config = []): array
    {
        $routes = [];
        $routeCollection = $router->getRouteCollection();

        foreach ($routeCollection->all() as $routeName => $route) {
            // 過濾不需要的路由
            if (!$this->shouldIncludeRoute($route, $routeName, $config)) {
                continue;
            }

            $controller = $this->extractControllerCallable($route);
            if (null === $controller) {
                continue;
            }

            [$controllerClass, $methodName] = $controller;

            try {
                $reflectionMethod = $this->getReflectionMethod($controllerClass, $methodName);
            } catch (\ReflectionException $e) {
                // 無法反射,跳過此路由
                continue;
            }

            $routes[$routeName] = [
                'route' => $route,
                'controller' => $controllerClass,
                'method' => $methodName,
                'reflection' => $reflectionMethod,
            ];
        }

        return $routes;
    }

    /**
     * 判斷路由是否應該被包含在文檔中.
     *
     * @param array<string, mixed> $config
     */
    private function shouldIncludeRoute(Route $route, string $routeName, array $config): bool
    {
        // 排除內部路由(以 _ 開頭)
        $includeInternal = $config['analysis']['include_internal_routes'] ?? false;
        if (!$includeInternal && str_starts_with($routeName, '_')) {
            return false;
        }

        // 確保路由有 controller
        if (!$route->hasDefault('_controller')) {
            return false;
        }

        return true;
    }

    /**
     * 從 Route 中提取 Controller callable.
     *
     * @return array{0: class-string, 1: string}|null [ClassName, methodName] or null
     */
    private function extractControllerCallable(Route $route): ?array
    {
        $controller = $route->getDefault('_controller');

        if (\is_array($controller) && 2 === \count($controller)) {
            [$classOrObject, $methodName] = $controller;

            if (\is_object($classOrObject) && \is_string($methodName)) {
                return [$classOrObject::class, $methodName];
            }

            if (\is_string($classOrObject) && class_exists($classOrObject) && \is_string($methodName)) {
                return [$classOrObject, $methodName];
            }
        }

        if (\is_object($controller) && !$controller instanceof \Closure && method_exists($controller, '__invoke')) {
            return [$controller::class, '__invoke'];
        }

        if (\is_string($controller)) {
            // 格式: ClassName::methodName
            if (str_contains($controller, '::')) {
                $parts = explode('::', $controller, 2);
                if (2 === \count($parts) && class_exists($parts[0])) {
                    return [$parts[0], $parts[1]];
                }
            }

            if (class_exists($controller) && method_exists($controller, '__invoke')) {
                return [$controller, '__invoke'];
            }
        }

        return null;
    }

    /**
     * 取得 ReflectionMethod.
     *
     * @param class-string $className
     *
     * @throws \ReflectionException
     */
    private function getReflectionMethod(string $className, string $methodName): \ReflectionMethod
    {
        $reflectionClass = new \ReflectionClass($className);

        return $reflectionClass->getMethod($methodName);
    }
}
