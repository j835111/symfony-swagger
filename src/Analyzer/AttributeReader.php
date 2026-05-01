<?php

declare(strict_types=1);

namespace SymfonySwagger\Analyzer;

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
     * @return object|null Route Attribute instance or null if not found
     */
    public function readRouteAttribute(\ReflectionMethod $method): ?object
    {
        // Symfony 6.0-6.2 use Annotation\Route, 6.3+ use Attribute\Route
        $routeClasses = [
            'Symfony\Component\Routing\Attribute\Route',
            'Symfony\Component\Routing\Annotation\Route',
        ];

        foreach ($routeClasses as $class) {
            if (class_exists($class)) {
                $attributes = $method->getAttributes($class, \ReflectionAttribute::IS_INSTANCEOF);
                if (!empty($attributes)) {
                    return $attributes[0]->newInstance();
                }
            }
        }

        return null;
    }

    /**
     * 讀取請求相關的 Attributes (#[MapRequestPayload], #[MapUploadedFile] 等).
     *
     * @return array<string, object> Map of attribute type => attribute instance
     */
    public function readRequestAttributes(\ReflectionMethod $method): array
    {
        $requestAttributes = [];

        foreach ($method->getParameters() as $parameter) {
            // #[MapRequestPayload] (Symfony 6.2+)
            if (class_exists('Symfony\Component\HttpKernel\Attribute\MapRequestPayload')) {
                $mapRequestPayload = $this->getParameterAttribute($parameter, 'Symfony\Component\HttpKernel\Attribute\MapRequestPayload');
                if (null !== $mapRequestPayload) {
                    $requestAttributes['requestPayload'] = $mapRequestPayload;
                }
            }

            // #[MapUploadedFile] (Symfony 6.2+)
            if (class_exists('Symfony\Component\HttpKernel\Attribute\MapUploadedFile')) {
                $mapUploadedFile = $this->getParameterAttribute($parameter, 'Symfony\Component\HttpKernel\Attribute\MapUploadedFile');
                if (null !== $mapUploadedFile) {
                    $requestAttributes['uploadedFile'] = $mapUploadedFile;
                }
            }

            // #[MapQueryString] (Symfony 6.2+)
            if (class_exists('Symfony\Component\HttpKernel\Attribute\MapQueryString')) {
                $mapQueryString = $this->getParameterAttribute($parameter, 'Symfony\Component\HttpKernel\Attribute\MapQueryString');
                if (null !== $mapQueryString) {
                    $requestAttributes['queryString'] = $mapQueryString;
                }
            }
        }

        return $requestAttributes;
    }

    /**
     * 從 Attributes 提取參數資訊.
     *
     * @return array<int, array<string, mixed>> Array of parameter definitions
     */
    public function getParametersFromAttributes(\ReflectionMethod $method): array
    {
        $parameters = [];

        foreach ($method->getParameters() as $parameter) {
            // #[MapQueryParameter] (Symfony 6.3+)
            if (class_exists('Symfony\Component\HttpKernel\Attribute\MapQueryParameter')) {
                $mapQueryParameter = $this->getParameterAttribute($parameter, 'Symfony\Component\HttpKernel\Attribute\MapQueryParameter');
                if (null !== $mapQueryParameter) {
                    $parameters[] = [
                        'name' => $parameter->getName(),
                        'in' => 'query',
                        'attribute' => $mapQueryParameter,
                        'type' => $parameter->getType(),
                    ];
                }
            }
        }

        return $parameters;
    }

    /**
     * 從 #[MapQueryString] 提取查詢參數 DTO.
     *
     * @return array<int, array<string, mixed>> Array of MapQueryString parameter definitions
     */
    public function getQueryStringParametersFromAttributes(\ReflectionMethod $method): array
    {
        $parameters = [];

        if (!class_exists('Symfony\Component\HttpKernel\Attribute\MapQueryString')) {
            return $parameters;
        }

        foreach ($method->getParameters() as $parameter) {
            $mapQueryString = $this->getParameterAttribute($parameter, 'Symfony\Component\HttpKernel\Attribute\MapQueryString');
            if (null !== $mapQueryString) {
                $parameters[] = [
                    'name' => $parameter->getName(),
                    'in' => 'query',
                    'attribute' => $mapQueryString,
                    'type' => $parameter->getType(),
                    'parameter' => $parameter,
                ];
            }
        }

        return $parameters;
    }

    /**
     * 從 #[MapUploadedFile] 提取檔案參數.
     *
     * @return array<int, array<string, mixed>> Array of MapUploadedFile parameter definitions
     */
    public function getUploadedFileParametersFromAttributes(\ReflectionMethod $method): array
    {
        $parameters = [];

        if (!class_exists('Symfony\Component\HttpKernel\Attribute\MapUploadedFile')) {
            return $parameters;
        }

        foreach ($method->getParameters() as $parameter) {
            $mapUploadedFile = $this->getParameterAttribute($parameter, 'Symfony\Component\HttpKernel\Attribute\MapUploadedFile');
            if (null !== $mapUploadedFile) {
                $parameters[] = [
                    'name' => $parameter->getName(),
                    'attribute' => $mapUploadedFile,
                    'type' => $parameter->getType(),
                    'parameter' => $parameter,
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
    public function readSecurityAttributes(\ReflectionMethod $method): array
    {
        // Check if IsGranted class exists (symfony/security-http may not be installed)
        if (!class_exists('Symfony\Component\Security\Http\Attribute\IsGranted')) {
            return [];
        }

        $classAttributes = $method
            ->getDeclaringClass()
            ->getAttributes('Symfony\Component\Security\Http\Attribute\IsGranted', \ReflectionAttribute::IS_INSTANCEOF);
        $methodAttributes = $method->getAttributes('Symfony\Component\Security\Http\Attribute\IsGranted', \ReflectionAttribute::IS_INSTANCEOF);

        return array_map(
            fn (\ReflectionAttribute $attr) => $attr->newInstance(),
            array_merge($classAttributes, $methodAttributes),
        );
    }

    /**
     * 讀取方法上的 #[ApiResponse] Attribute.
     */
    public function readApiResponseAttribute(\ReflectionMethod $method): ?\SymfonySwagger\Attribute\ApiResponse
    {
        $attributes = $method->getAttributes(\SymfonySwagger\Attribute\ApiResponse::class);

        if (empty($attributes)) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * 從參數中取得特定 Attribute.
     *
     * @template T of object
     *
     * @param class-string<T> $attributeClass
     *
     * @return T|null
     */
    private function getParameterAttribute(\ReflectionParameter $parameter, string $attributeClass): ?object
    {
        $attributes = $parameter->getAttributes($attributeClass, \ReflectionAttribute::IS_INSTANCEOF);

        if (empty($attributes)) {
            return null;
        }

        return $attributes[0]->newInstance();
    }
}
