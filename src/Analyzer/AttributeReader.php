<?php

declare(strict_types=1);

namespace SymfonySwagger\Analyzer;

use ReflectionAttribute;
use ReflectionMethod;
use ReflectionParameter;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Routing\Attribute\Route;

/**
 * AttributeReader - 讀取 PHP Attributes.
 *
 * 負責從 ReflectionMethod 中擷取 Symfony Attributes,
 * 包括路由、請求參數映射、安全性等 Attributes。
 */
class AttributeReader
{
    /**
     * 讀取 #[Route] Attribute.
     *
     * @return Route|null Route Attribute instance or null if not found
     */
    public function readRouteAttribute(ReflectionMethod $method): ?Route
    {
        $attributes = $method->getAttributes(Route::class, ReflectionAttribute::IS_INSTANCEOF);

        if (empty($attributes)) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * 讀取請求相關的 Attributes (#[MapRequestPayload], #[MapUploadedFile] 等).
     *
     * @return array<string, object> Map of attribute type => attribute instance
     */
    public function readRequestAttributes(ReflectionMethod $method): array
    {
        $requestAttributes = [];

        foreach ($method->getParameters() as $parameter) {
            // #[MapRequestPayload]
            $mapRequestPayload = $this->getParameterAttribute($parameter, MapRequestPayload::class);
            if ($mapRequestPayload !== null) {
                $requestAttributes['requestPayload'] = $mapRequestPayload;
            }

            // #[MapUploadedFile]
            $mapUploadedFile = $this->getParameterAttribute($parameter, MapUploadedFile::class);
            if ($mapUploadedFile !== null) {
                $requestAttributes['uploadedFile'] = $mapUploadedFile;
            }

            // #[MapQueryString]
            $mapQueryString = $this->getParameterAttribute($parameter, MapQueryString::class);
            if ($mapQueryString !== null) {
                $requestAttributes['queryString'] = $mapQueryString;
            }
        }

        return $requestAttributes;
    }

    /**
     * 從 Attributes 提取參數資訊.
     *
     * @return array<int, array<string, mixed>> Array of parameter definitions
     */
    public function getParametersFromAttributes(ReflectionMethod $method): array
    {
        $parameters = [];

        foreach ($method->getParameters() as $parameter) {
            // #[MapQueryParameter]
            $mapQueryParameter = $this->getParameterAttribute($parameter, MapQueryParameter::class);
            if ($mapQueryParameter !== null) {
                $parameters[] = [
                    'name' => $parameter->getName(),
                    'in' => 'query',
                    'attribute' => $mapQueryParameter,
                    'type' => $parameter->getType(),
                ];
            }
        }

        return $parameters;
    }

    /**
     * 讀取安全性相關 Attributes (#[IsGranted]).
     *
     * @return array<int, object> Array of security attributes
     */
    public function readSecurityAttributes(ReflectionMethod $method): array
    {
        // Check if IsGranted class exists (symfony/security-http may not be installed)
        if (!class_exists('Symfony\Component\Security\Http\Attribute\IsGranted')) {
            return [];
        }

        $attributes = $method->getAttributes('Symfony\Component\Security\Http\Attribute\IsGranted', ReflectionAttribute::IS_INSTANCEOF);

        return array_map(
            fn (ReflectionAttribute $attr) => $attr->newInstance(),
            $attributes
        );
    }

    /**
     * 從參數中取得特定 Attribute.
     *
     * @template T of object
     * @param class-string<T> $attributeClass
     * @return T|null
     */
    private function getParameterAttribute(ReflectionParameter $parameter, string $attributeClass): ?object
    {
        $attributes = $parameter->getAttributes($attributeClass, ReflectionAttribute::IS_INSTANCEOF);

        if (empty($attributes)) {
            return null;
        }

        return $attributes[0]->newInstance();
    }
}
