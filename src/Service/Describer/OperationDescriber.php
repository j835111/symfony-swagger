<?php

declare(strict_types=1);

namespace SymfonySwagger\Service\Describer;

use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Route;
use SymfonySwagger\Analyzer\AttributeReader;
use SymfonySwagger\Analyzer\TypeAnalyzer;

/**
 * OperationDescriber - 操作描述器.
 *
 * 負責分析 Controller 方法並生成 OpenAPI Operation 定義。
 */
class OperationDescriber
{
    public function __construct(
        private readonly AttributeReader $attributeReader,
        private readonly TypeAnalyzer $typeAnalyzer,
        private readonly SchemaDescriber $schemaDescriber,
    ) {
    }

    /**
     * 描述一個 Controller 方法的操作.
     *
     * @return array<string, mixed>
     */
    public function describe(\ReflectionMethod $method, Route $route): array
    {
        $operation = [
            'summary' => $this->generateSummary($method),
            'operationId' => $this->generateOperationId($method),
            'tags' => $this->generateTags($method),
        ];

        // Parameters (path, query)
        $parameters = $this->describeParameters($method, $route);
        if (!empty($parameters)) {
            $operation['parameters'] = $parameters;
        }

        // Request Body
        $requestBody = $this->describeRequestBody($method);
        if (null !== $requestBody) {
            $operation['requestBody'] = $requestBody;
        }

        // Responses
        $operation['responses'] = $this->describeResponses($method);

        return $operation;
    }

    /**
     * 描述參數.
     *
     * @return list<array<string, mixed>>
     */
    private function describeParameters(\ReflectionMethod $method, Route $route): array
    {
        $parameters = [];

        // Path parameters from route requirements
        $path = $route->getPath();
        preg_match_all('/\{(\w+)\}/', $path, $matches);
        foreach ($matches[1] as $paramName) {
            $parameters[] = [
                'name' => $paramName,
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'string'],
            ];
        }

        // Query parameters from #[MapQueryParameter]
        $queryParams = $this->attributeReader->getParametersFromAttributes($method);
        foreach ($queryParams as $param) {
            $schema = $this->typeAnalyzer->analyze($param['type']);
            $parameters[] = [
                'name' => $param['name'],
                'in' => 'query',
                'required' => null !== $param['type'] && !$param['type']->allowsNull(),
                'schema' => $schema,
            ];
        }

        return $parameters;
    }

    /**
     * 描述請求體.
     *
     * @return array<string, mixed>|null
     */
    private function describeRequestBody(\ReflectionMethod $method): ?array
    {
        $requestAttributes = $this->attributeReader->readRequestAttributes($method);

        // Check for #[MapRequestPayload]
        if (isset($requestAttributes['requestPayload'])) {
            $payload = $requestAttributes['requestPayload'];

            // 找到對應的參數
            foreach ($method->getParameters() as $parameter) {
                $attrs = $parameter->getAttributes(MapRequestPayload::class);
                if (!empty($attrs)) {
                    $type = $parameter->getType();
                    if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                        $className = $type->getName();
                        $reflectionClass = new \ReflectionClass($className);
                        $schema = $this->schemaDescriber->describe($reflectionClass);

                        return [
                            'required' => !$type->allowsNull(),
                            'content' => [
                                'application/json' => [
                                    'schema' => $schema,
                                ],
                            ],
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * 描述回應.
     *
     * @return array<int|string, mixed>
     */
    private function describeResponses(\ReflectionMethod $method): array
    {
        $returnType = $method->getReturnType();

        $responses = [
            '200' => [
                'description' => 'Successful operation',
            ],
        ];

        if ($returnType instanceof \ReflectionNamedType && !$returnType->isBuiltin()) {
            $className = $returnType->getName();
            if (class_exists($className)) {
                try {
                    $reflectionClass = new \ReflectionClass($className);
                    $schema = $this->schemaDescriber->describe($reflectionClass);

                    $responses['200']['content'] = [
                        'application/json' => [
                            'schema' => $schema,
                        ],
                    ];
                } catch (\ReflectionException $e) {
                    // Ignore
                }
            }
        }

        return $responses;
    }

    /**
     * 生成操作摘要.
     */
    private function generateSummary(\ReflectionMethod $method): string
    {
        // 從 DocBlock 提取或使用方法名稱
        $docComment = $method->getDocComment();
        if (false !== $docComment && preg_match('/@summary\s+(.+)/', $docComment, $matches)) {
            return trim($matches[1]);
        }

        // 從方法名稱生成
        $methodName = $method->getName();

        return ucfirst(preg_replace('/([a-z])([A-Z])/', '$1 $2', $methodName));
    }

    /**
     * 生成 operationId.
     */
    private function generateOperationId(\ReflectionMethod $method): string
    {
        $className = $method->getDeclaringClass()->getShortName();
        $methodName = $method->getName();

        return lcfirst($className).'_'.$methodName;
    }

    /**
     * 生成標籤.
     *
     * @return list<string>
     */
    private function generateTags(\ReflectionMethod $method): array
    {
        // 使用 Controller 名稱作為標籤
        $className = $method->getDeclaringClass()->getShortName();
        $tag = str_replace('Controller', '', $className);

        return [$tag];
    }
}
