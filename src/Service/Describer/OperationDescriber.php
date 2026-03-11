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

                    // 情況 1: 參數型別直接是 DTO class (e.g., UpdateRequest $item)
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

                    // 情況 2: 參數型別為 array，透過 #[MapRequestPayload(type: Dto::class)] 指定 DTO
                    // e.g., #[MapRequestPayload(type: UpdateRequest::class)] array $items
                    if ($type instanceof \ReflectionNamedType && $type->getName() === 'array') {
                        $attrInstance = $attrs[0]->newInstance();
                        $dtoClass = $attrInstance->type ?? null;
                        if (null !== $dtoClass && class_exists($dtoClass)) {
                            $reflectionClass = new \ReflectionClass($dtoClass);
                            $itemSchema = $this->schemaDescriber->describe($reflectionClass);

                            return [
                                'required' => true,
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'array',
                                            'items' => $itemSchema,
                                        ],
                                    ],
                                ],
                            ];
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * 描述回應.
     *
     * - 若 #[ApiResponse(file: true)] → 檔案下載 schema（binary，無 JSON 信封）
     * - 其他情況 → 標準 JSON 信封：{ code: int, message: string, data: <DTO|DTO[]|null> }
     *
     * @return array<int|string, mixed>
     */
    private function describeResponses(\ReflectionMethod $method): array
    {
        $apiResponse = $this->attributeReader->readApiResponseAttribute($method);

        if (null !== $apiResponse && $apiResponse->file) {
            return $this->buildFileResponse($apiResponse->fileMediaType);
        }

        return [
            '200' => [
                'description' => 'Successful operation',
                'content' => [
                    'application/json' => [
                        'schema' => $this->buildEnvelopeSchema($method),
                    ],
                ],
            ],
        ];
    }

    /**
     * 建立檔案下載 response 定義.
     *
     * 產生結構：
     * 200:
     *   description: File download
     *   headers:
     *     Content-Disposition: { schema: { type: string } }
     *   content:
     *     <mediaType>:
     *       schema: { type: string, format: binary }
     *
     * @return array<int|string, mixed>
     */
    private function buildFileResponse(string $mediaType): array
    {
        return [
            '200' => [
                'description' => 'File download',
                'headers' => [
                    'Content-Disposition' => [
                        'description' => 'Attachment filename',
                        'schema' => ['type' => 'string'],
                    ],
                ],
                'content' => [
                    $mediaType => [
                        'schema' => [
                            'type' => 'string',
                            'format' => 'binary',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * 建立標準信封 schema.
     *
     * 結構：{ code: int, message: string, data: <DTO|DTO[]|null> }
     *
     * @return array<string, mixed>
     */
    private function buildEnvelopeSchema(\ReflectionMethod $method): array
    {
        $properties = [
            'code' => ['type' => 'integer', 'example' => 200],
            'message' => ['type' => 'string', 'example' => 'success'],
            'data' => $this->resolveDataSchema($method),
        ];

        return [
            'type' => 'object',
            'properties' => $properties,
                    ];
                }

    /**
     * 解析 data 欄位的 schema.
     *
     * 優先使用 #[ApiResponse(type: DtoClass::class)] Attribute；
     * 若未標注則 data 設為 nullable object。
     *
     * @return array<string, mixed>
     */
    private function resolveDataSchema(\ReflectionMethod $method): array
    {
        $apiResponse = $this->attributeReader->readApiResponseAttribute($method);

        if (null === $apiResponse || null === $apiResponse->type) {
            return ['nullable' => true, 'description' => 'Response data'];
            }

        $dtoClass = $apiResponse->type;

        if (!class_exists($dtoClass)) {
            return ['nullable' => true, 'description' => 'Response data'];
        }

        try {
            $reflectionClass = new \ReflectionClass($dtoClass);
            $itemSchema = $this->schemaDescriber->describe($reflectionClass);

            if ($apiResponse->collection) {
                return [
                    'type' => 'array',
                    'items' => $itemSchema,
                ];
            }

            return $itemSchema;
        } catch (\ReflectionException) {
            return ['nullable' => true, 'description' => 'Response data'];
        }
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
