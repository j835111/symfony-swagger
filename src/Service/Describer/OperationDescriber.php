<?php

declare(strict_types=1);

namespace SymfonySwagger\Service\Describer;

use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Route;
use SymfonySwagger\Analyzer\AttributeReader;
use SymfonySwagger\Analyzer\DocBlockDescriptionExtractor;
use SymfonySwagger\Analyzer\TypeAnalyzer;

/**
 * OperationDescriber - 操作描述器.
 *
 * 負責分析 Controller 方法並生成 OpenAPI Operation 定義。
 */
class OperationDescriber
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly AttributeReader $attributeReader,
        private readonly TypeAnalyzer $typeAnalyzer,
        private readonly SchemaDescriber $schemaDescriber,
        private readonly array $config = [],
    ) {
    }

    /**
     * 描述一個 Controller 方法的操作.
     *
     * @return array<string, mixed>
     */
    public function describe(\ReflectionMethod $method, Route $route, ?string $httpMethod = null): array
    {
        $operation = [
            'summary' => $this->generateSummary($method),
            'operationId' => $this->generateOperationId($method),
            'tags' => $this->generateTags($method),
        ];

        $description = $this->generateDescription($method);
        if (null !== $description) {
            $operation['description'] = $description;
        }

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

        // Security
        $security = $this->describeSecurity($method, $route, $httpMethod);
        if (null !== $security) {
            $operation['security'] = $security;
        }

        // Responses
        $operation['responses'] = $this->describeResponses($method);

        return $operation;
    }

    /**
     * @return list<array<string, list<string>>>|null
     */
    private function describeSecurity(\ReflectionMethod $method, Route $route, ?string $httpMethod): ?array
    {
        if (false === ($this->config['security']['enabled'] ?? true)) {
            return null;
        }

        $securityAttributes = $this->attributeReader->readSecurityAttributes($method);
        if (empty($securityAttributes)) {
            return null;
        }

        $operationMethod = $this->resolveOperationHttpMethod($route, $httpMethod);
        foreach ($securityAttributes as $attribute) {
            if (!$this->securityAttributeAppliesToMethod($attribute, $operationMethod)) {
                continue;
            }

            if ($this->isPublicAccessSecurityAttribute($attribute)) {
                continue;
            }

            return [
                [
                    $this->getDefaultSecuritySchemeName() => [],
                ],
            ];
        }

        return null;
    }

    private function isPublicAccessSecurityAttribute(object $attribute): bool
    {
        if (!property_exists($attribute, 'attribute')) {
            return false;
        }

        $securityAttribute = $attribute->attribute;
        if (\is_string($securityAttribute)) {
            return 'PUBLIC_ACCESS' === $securityAttribute;
        }

        if (\is_array($securityAttribute)) {
            return \in_array('PUBLIC_ACCESS', $securityAttribute, true);
        }

        return false;
    }

    private function resolveOperationHttpMethod(Route $route, ?string $httpMethod): ?string
    {
        if (null !== $httpMethod) {
            return strtoupper($httpMethod);
        }

        $methods = $route->getMethods();
        if (1 === \count($methods)) {
            return strtoupper($methods[0]);
        }

        return null;
    }

    private function securityAttributeAppliesToMethod(object $attribute, ?string $httpMethod): bool
    {
        if (!property_exists($attribute, 'methods')) {
            return true;
        }

        $methods = $attribute->methods;
        if (null === $methods || [] === $methods) {
            return true;
        }

        if (null === $httpMethod) {
            return true;
        }

        if (\is_string($methods)) {
            return strtoupper($methods) === $httpMethod;
        }

        if (\is_array($methods)) {
            return \in_array($httpMethod, array_map('strtoupper', $methods), true);
        }

        return true;
    }

    private function getDefaultSecuritySchemeName(): string
    {
        $defaultScheme = $this->config['security']['default_scheme'] ?? 'defaultAuth';

        return \is_string($defaultScheme) && '' !== $defaultScheme ? $defaultScheme : 'defaultAuth';
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
            $parameter = [
                'name' => $paramName,
                'in' => 'path',
                'required' => true,
                'schema' => $this->describePathParameterSchema($method, $route, $paramName),
            ];
            $this->applyParameterDescription($parameter, DocBlockDescriptionExtractor::getParameterDescription($method, $paramName));
            $parameters[] = $parameter;
        }

        // Query parameters from #[MapQueryParameter]
        $queryParams = $this->attributeReader->getParametersFromAttributes($method);
        foreach ($queryParams as $param) {
            $schema = $this->typeAnalyzer->analyze($param['type']);
            $parameter = [
                'name' => $param['name'],
                'in' => 'query',
                'required' => null !== $param['type'] && !$param['type']->allowsNull(),
                'schema' => $schema,
            ];
            $this->applyParameterDescription($parameter, DocBlockDescriptionExtractor::getParameterDescription($method, $param['name']));
            $parameters[] = $parameter;
        }

        // Query parameters from #[MapQueryString]
        $queryStringParams = $this->attributeReader->getQueryStringParametersFromAttributes($method);
        foreach ($queryStringParams as $param) {
            $parameter = $param['parameter'];
            $type = $param['type'];
            $attr = $param['attribute'];

            if (!($type instanceof \ReflectionNamedType) || $type->isBuiltin() || !class_exists($type->getName())) {
                continue;
            }

            $reflectionClass = new \ReflectionClass($type->getName());
            $properties = [];
            $required = [];

            foreach ($reflectionClass->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
                if ($property->isStatic()) {
                    continue;
                }

                $properties[$property->getName()] = $this->typeAnalyzer->analyzeProperty($property);

                $propType = $property->getType();
                if (null !== $propType && !$propType->allowsNull() && !$property->hasDefaultValue()) {
                    $required[] = $property->getName();
                }
            }

            $key = null;
            if (\is_object($attr) && property_exists($attr, 'key') && \is_string($attr->key) && '' !== $attr->key) {
                $key = $attr->key;
            }

            $paramIsOptional = $parameter->allowsNull() || $parameter->isDefaultValueAvailable();

            if (null !== $key) {
                $schema = [
                    'type' => 'object',
                    'properties' => $properties,
                ];
                if (!empty($required)) {
                    $schema['required'] = $required;
                }

                $parameters[] = [
                    'name' => $key,
                    'in' => 'query',
                    'required' => !$paramIsOptional,
                    'style' => 'deepObject',
                    'explode' => true,
                    'schema' => $schema,
                ];
                $lastIndex = array_key_last($parameters);
                if (null !== $lastIndex) {
                    $this->applyParameterDescription($parameters[$lastIndex], DocBlockDescriptionExtractor::getParameterDescription($method, $parameter->getName()));
                }
            } else {
                foreach ($properties as $name => $schema) {
                    $isRequired = !$paramIsOptional && \in_array($name, $required, true);
                    $parameterDefinition = [
                        'name' => $name,
                        'in' => 'query',
                        'required' => $isRequired,
                        'schema' => $schema,
                    ];
                    $this->applyParameterDescription($parameterDefinition, $schema['description'] ?? null);
                    $parameters[] = $parameterDefinition;
                }
            }
        }

        return $parameters;
    }

    /**
     * @return array<string, mixed>
     */
    private function describePathParameterSchema(\ReflectionMethod $method, Route $route, string $paramName): array
    {
        foreach ($method->getParameters() as $parameter) {
            if ($parameter->getName() !== $paramName) {
                continue;
            }

            $schema = $this->typeAnalyzer->analyze($parameter->getType());
            unset($schema['nullable']);

            return $schema;
        }

        $requirement = $route->getRequirement($paramName);
        if (null !== $requirement && preg_match('/(?:\\\\d\+|\[0-9\]\+|\^?\d\+\$?)/', $requirement)) {
            return ['type' => 'integer', 'format' => 'int32'];
        }

        return ['type' => 'string'];
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

                        $requestBody = [
                            'required' => !$type->allowsNull(),
                            'content' => [
                                'application/json' => [
                                    'schema' => $schema,
                                ],
                            ],
                        ];
                        $this->applyDescription($requestBody, DocBlockDescriptionExtractor::getParameterDescription($method, $parameter->getName()));

                        return $requestBody;
                    }

                    // 情況 2: 參數型別為 array，透過 #[MapRequestPayload(type: Dto::class)] 指定 DTO
                    // e.g., #[MapRequestPayload(type: UpdateRequest::class)] array $items
                    if ($type instanceof \ReflectionNamedType && 'array' === $type->getName()) {
                        $attrInstance = $attrs[0]->newInstance();
                        $dtoClass = $attrInstance->type ?? null;
                        if (null !== $dtoClass && class_exists($dtoClass)) {
                            $reflectionClass = new \ReflectionClass($dtoClass);
                            $itemSchema = $this->schemaDescriber->describe($reflectionClass);

                            $requestBody = [
                                'required' => !$type->allowsNull(),
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'array',
                                            'items' => $itemSchema,
                                        ],
                                    ],
                                ],
                            ];
                            $this->applyDescription($requestBody, DocBlockDescriptionExtractor::getParameterDescription($method, $parameter->getName()));

                            return $requestBody;
                        }
                    }
                }
            }
        }

        // Check for #[MapUploadedFile]
        $uploadedFiles = $this->attributeReader->getUploadedFileParametersFromAttributes($method);
        if (!empty($uploadedFiles)) {
            return $this->buildMultipartRequestBody($method, $uploadedFiles);
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
                        'schema' => $this->buildEnvelopeSchema($apiResponse),
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
     * 建立 multipart/form-data requestBody (uploaded files).
     *
     * @param array<int, array<string, mixed>> $uploadedFiles
     *
     * @return array<string, mixed>
     */
    private function buildMultipartRequestBody(\ReflectionMethod $method, array $uploadedFiles): array
    {
        $properties = [];
        $required = [];

        foreach ($uploadedFiles as $fileParam) {
            $parameter = $fileParam['parameter'];
            $attr = $fileParam['attribute'];
            $fieldName = $fileParam['name'];

            if (\is_object($attr) && property_exists($attr, 'name') && \is_string($attr->name) && '' !== $attr->name) {
                $fieldName = $attr->name;
            }

            $isArray = $parameter->isVariadic();
            $type = $fileParam['type'];
            if ($type instanceof \ReflectionNamedType && 'array' === $type->getName()) {
                $isArray = true;
            }

            $schema = $isArray
                ? ['type' => 'array', 'items' => ['type' => 'string', 'format' => 'binary']]
                : ['type' => 'string', 'format' => 'binary'];

            $description = DocBlockDescriptionExtractor::getParameterDescription($method, $parameter->getName());
            if (null !== $description) {
                $schema['description'] = $description;
            }

            $properties[$fieldName] = $schema;

            $paramRequired = !($parameter->allowsNull() || $parameter->isDefaultValueAvailable());
            if ($paramRequired) {
                $required[] = $fieldName;
            }
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if (!empty($required)) {
            $schema['required'] = array_values(array_unique($required));
        }

        return [
            'required' => !empty($required),
            'content' => [
                'multipart/form-data' => [
                    'schema' => $schema,
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
    private function buildEnvelopeSchema(?\SymfonySwagger\Attribute\ApiResponse $apiResponse): array
    {
        $properties = [
            'code' => ['type' => 'integer', 'example' => 200],
            'message' => ['type' => 'string', 'example' => 'success'],
            'data' => $this->resolveDataSchema($apiResponse),
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
    private function resolveDataSchema(?\SymfonySwagger\Attribute\ApiResponse $apiResponse): array
    {
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
        $summary = DocBlockDescriptionExtractor::getSummary($method);
        if (null !== $summary) {
            return $summary;
        }

        // 從方法名稱生成
        $methodName = $method->getName();

        return ucfirst(preg_replace('/([a-z])([A-Z])/', '$1 $2', $methodName));
    }

    private function generateDescription(\ReflectionMethod $method): ?string
    {
        return DocBlockDescriptionExtractor::getOperationDescription($method);
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

    /**
     * @param array<string, mixed> $parameter
     */
    private function applyParameterDescription(array &$parameter, ?string $description): void
    {
        $this->applyDescription($parameter, $description);
    }

    /**
     * @param array<string, mixed> $target
     */
    private function applyDescription(array &$target, ?string $description): void
    {
        if (null === $description || '' === trim($description)) {
            return;
        }

        $target['description'] = $description;
    }
}
