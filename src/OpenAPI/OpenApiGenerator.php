<?php

declare(strict_types=1);

namespace ApiPlatform\OpenAPI;

use ApiPlatform\Core\Provider\Endpoints\IEndpointsProvider;
use ApiPlatform\DTO\ApiEndpointDTO;
use ApiPlatform\Endpoint\Schema;
use ApiPlatform\Exception\InternalErrorException;
use ApiPlatform\IApi;
use ApiPlatform\OpenAPI\DTO\OpenApiEndpointInfoDTO;
use ApiPlatform\OpenAPI\DTO\OpenApiEndpointRequestParameterDTO;
use ApiPlatform\OpenAPI\DTO\OpenApiEndpointResponseParameterDTO;
use ApiPlatform\OpenAPI\DTO\OpenApiEndpointResponseSchemaDTO;
use ApiPlatform\OpenAPI\Enum\OpenApiEndpointParameterTypeEnum;
use ApiPlatform\OpenAPI\Provider\IOpenApiEndpointInfoProvider;

class OpenApiGenerator implements IOpenApiGenerator {
    /** @var array<string, array<string, mixed>> Path => method => operation. */
    protected array $paths = [];

    /** @var array<string, array<string, mixed>> Name => schema. */
    protected array $schemas = [];

    /**
     * @param string $title       The title of the spec (api_title parameter).
     * @param string $description The description of the spec (api_description parameter).
     */
    public function __construct(
        protected IEndpointsProvider           $endpointsProvider,
        protected IOpenApiEndpointInfoProvider $endpointInfoProvider,
        protected IApi                         $api,
        protected string                       $endpointPrefix,
        protected string                       $tokenHeader,
        protected string                       $title = 'API',
        protected string                       $description = ''
    ) {}

    /**
     * @throws InternalErrorException
     */
    public function generateOpenApiSpec(): array {
        $this->definePathsAndSchemas();

        ksort($this->paths, SORT_STRING);

        return [
            'openapi'    => $this->getOpenApiVersion(),
            'info'       => $this->getInfo(),
            'servers'    => $this->getServers(),
            'paths'      => $this->paths,
            'components' => $this->getComponents()
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getComponents(): array {
        ksort($this->schemas, SORT_STRING);

        return [
            'schemas'         => $this->schemas,
            'securitySchemes' => $this->getSecuritySchemes()
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    protected function getSecuritySchemes(): array {
        return [
            'Token' => [
                'type'        => 'apiKey',
                'description' => "Value for the $this->tokenHeader header parameter.",
                'name'        => $this->tokenHeader,
                'in'          => "header"
            ]
        ];
    }

    protected function getOpenApiVersion(): string {
        return '3.0.0';
    }

    /**
     * @return array<string, string>
     */
    protected function getInfo(): array {
        return [
            'title'       => $this->title,
            'description' => $this->description,
            'version'     => $this->api->getVersion()
        ];
    }

    /**
     * @return array<array<string, string>>
     */
    protected function getServers(): array {
        return [
            [
                'url'         => '/',
                'description' => ''
            ]
        ];
    }

    /**
     * @throws InternalErrorException
     */
    protected function definePathsAndSchemas(): void {
        $this->paths = [];
        $this->schemas = $this->getErrorSchema();

        foreach ($this->getApiEndpoints() as $endpoint) {
            $endpointInfo = $this->endpointInfoProvider->getEndpointInfo($endpoint);

            $endpointPath = $this->getOpenApiPath($endpoint);
            $this->paths[$endpointPath] = [
                ...($this->paths[$endpointPath] ?? []),
                ...$this->getEndpointPathInfo($endpoint, $endpointInfo)
            ];
        }
    }

    /**
     * The endpoint path templates have the OpenAPI syntax, but without the inline requirements: "{year<\d{4}>}" is
     * "{year}" (a requirement ends with ">}").
     */
    protected function getOpenApiPath(ApiEndpointDTO $endpoint): string {
        return rtrim($this->endpointPrefix, '/') . '/'
            . preg_replace('/\{(\w+)<.*?>}/', '{$1}', $endpoint->path);
    }

    /**
     * @return ApiEndpointDTO[]
     */
    protected function getApiEndpoints(): array {
        return array_values(
            array_filter(
                $this->endpointsProvider->getApiEndpoints(),
                fn(ApiEndpointDTO $endpoint) => $this->isIncludeEndpointToDocs($endpoint)
            )
        );
    }

    protected function isIncludeEndpointToDocs(ApiEndpointDTO $endpoint): bool {
        return !is_a($endpoint->class, Schema::class, true);
    }

    /**
     * @return array<string, array<string, mixed>>
     *
     * @throws InternalErrorException
     */
    protected function getEndpointPathInfo(ApiEndpointDTO $endpoint, OpenApiEndpointInfoDTO $endpointInfo): array {
        return [
            strtolower($endpoint->requestMethod) => $this->getEndpointInfo($endpointInfo)
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InternalErrorException
     */
    protected function getEndpointInfo(OpenApiEndpointInfoDTO $endpointInfo): array {
        $result = [];

        if ($endpointInfo->operationId) {
            $result['operationId'] = $endpointInfo->operationId;
        }

        if ($endpointInfo->group) {
            $result['tags'] = [$endpointInfo->group];
        }

        if ($endpointInfo->description) {
            $result['description'] = $result['summary'] = $endpointInfo->description;
        }

        $result['responses'] = $this->getEndpointResponses($endpointInfo);

        // The token of the security scheme (see getSecuritySchemes()).
        if (!$endpointInfo->isPublic) {
            $result['security'] = [['Token' => []]];
        }

        if ($parameters = $this->getEndpointParameters($endpointInfo)) {
            $result['parameters'] = $parameters;
        }

        if ($bodyParameters = $this->getEndpointBodyParameters($endpointInfo)) {
            $result['requestBody'] = [
                'content' => [
                    $endpointInfo->responseMimeType => [
                        'schema' => $bodyParameters
                    ]
                ]
            ];
        }

        $result['deprecated'] = $endpointInfo->isDeprecated;

        return $result;
    }

    /**
     * @return array<string, mixed> The schema of the body object, empty if there are no body parameters.
     */
    protected function getEndpointBodyParameters(OpenApiEndpointInfoDTO $endpointInfo): array {
        $properties = [];
        $required = [];

        foreach ($this->getBodyParameters($endpointInfo) as $parameter) {
            // The required properties are listed by the object, "allowEmptyValue" is for the query only.
            $properties[$parameter->name] = array_diff_key(
                $this->getEndpointParameterSchema($parameter),
                array_flip(['required', 'allowEmptyValue'])
            );

            if ($parameter->isRequired) {
                $required[] = $parameter->name;
            }
        }

        if (!$properties) {
            return [];
        }

        return ['type' => 'object', 'properties' => $properties, ...($required ? ['required' => $required] : [])];
    }

    /**
     * @return array<array<string, mixed>>
     */
    protected function getEndpointParameters(OpenApiEndpointInfoDTO $endpointInfo): array {
        $parameters = [];

        // A Parameter Object: the name, the location and the documentation, the type details are in the schema.
        $parameterFields = ['description', 'example', 'required'];

        foreach ($this->getRequestParameters($endpointInfo) as $parameter) {
            $schema = $this->getEndpointParameterSchema($parameter);
            $result = ['name' => $parameter->name, 'in' => $parameter->source];

            foreach ($parameterFields as $field) {
                if (array_key_exists($field, $schema)) {
                    $result[$field] = $schema[$field];
                }
            }

            if ($parameter->source === 'query') {
                $result['allowEmptyValue'] = $parameter->allowEmptyValue;
            }

            $result['schema'] = array_diff_key($schema, array_flip([...$parameterFields, 'allowEmptyValue']));
            $parameters[] = $result;
        }

        return $parameters;
    }

    /**
     * @return OpenApiEndpointRequestParameterDTO[]
     */
    protected function getRequestParameters(OpenApiEndpointInfoDTO $endpointInfo): array {
        return array_values(
            array_filter(
                $endpointInfo->arguments,
                static fn(OpenApiEndpointRequestParameterDTO $parameter): bool => in_array(
                    $parameter->source,
                    ["query", "path"],
                    true
                )
            )
        );
    }

    /**
     * @return OpenApiEndpointRequestParameterDTO[]
     */
    protected function getBodyParameters(OpenApiEndpointInfoDTO $endpointInfo): array {
        return array_values(
            array_filter(
                $endpointInfo->arguments,
                static fn(OpenApiEndpointRequestParameterDTO $parameter): bool => in_array(
                    $parameter->source,
                    ["body", "file"],
                    true
                )
            )
        );
    }

    /**
     * @return array<int|string, array<string, mixed>> Code => response.
     *
     * @throws InternalErrorException
     */
    protected function getEndpointResponses(OpenApiEndpointInfoDTO $endpointInfo): array {
        $responses = [
            $this->getSuccessfulResponseCode($endpointInfo) => $this->getSuccessfulResponse($endpointInfo),
            '500'                                           => $this->getInternalErrorResponse()
        ];

        if (!$endpointInfo->isPublic) {
            $responses['401'] = $this->getUnauthorizedResponse();
        }

        foreach ($endpointInfo->throws as $throw) {
            $responses[$throw->code] = $this->getErrorResponse($throw->description);
        }

        ksort($responses);

        return $responses;
    }

    protected function getSuccessfulResponseCode(OpenApiEndpointInfoDTO $endpointInfo): string {
        return $this->isApiResponseEmpty($endpointInfo)
            ? '204'
            : '200';
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InternalErrorException
     */
    protected function getSuccessfulResponse(OpenApiEndpointInfoDTO $endpointInfo): array {
        // "204 No Content" has no body.
        if ($this->isApiResponseEmpty($endpointInfo)) {
            return ['description' => $endpointInfo->responseSchema->description];
        }

        return [
            'description' => $endpointInfo->responseSchema->description,
            'content'     => [
                'application/json' => [
                    'schema' => $this->getEndpointResponseSchema($endpointInfo->responseSchema)
                ]
            ]
        ];
    }

    protected function isApiResponseEmpty(OpenApiEndpointInfoDTO $endpointInfo): bool {
        return empty($endpointInfo->responseSchema->schema);
    }

    /**
     * @param OpenApiEndpointResponseSchemaDTO|string $schema A DTO schema (it goes to the components), or a PHP type
     *                                                        name of a primitive or a free-form value ("object",
     *                                                        "mixed").
     *
     * @return array<string, string>
     *
     * @throws InternalErrorException
     */
    protected function getEndpointResponseSchema(OpenApiEndpointResponseSchemaDTO|string $schema): array {
        if (is_string($schema)) {
            return match (strtolower($schema)) {
                'int'    => ['type' => 'integer'],
                'float'  => ['type' => 'number'],
                'bool'   => ['type' => 'boolean'],
                // Any JSON value.
                'mixed'  => [],
                default  => ['type' => strtolower($schema)],
            };
        }

        if (!isset($this->schemas[$schema->refName])) {
            // Registered before the properties: a DTO can refer to itself (e.g. a tree node).
            $this->schemas[$schema->refName] = [];
            $responseSchema = ['type' => 'object'];

            if ($schema->description) {
                $responseSchema['description'] = $schema->description;
            }

            foreach ($schema->schema as $field) {
                $responseSchema['properties'][$field->name] = $this->getFieldSchema($schema, $field);
            }

            $this->schemas[$schema->refName] = $responseSchema;
        }

        return [
            '$ref' => "#/components/schemas/$schema->refName"
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InternalErrorException
     */
    protected function getFieldSchema(
        OpenApiEndpointResponseSchemaDTO    $schema,
        OpenApiEndpointResponseParameterDTO $field
    ): array {
        if ($field->type === OpenApiEndpointParameterTypeEnum::TYPE_ARRAY) {
            if (!$field->children) {
                throw new InternalErrorException(
                    "Error while generating schema for '$schema->refName': field '$field->name' is array, but the items type is not defined."
                );
            }

            return [
                'type'  => "array",
                'items' => $this->getEndpointResponseSchema($field->children)
            ];
        }

        if ($field->type === OpenApiEndpointParameterTypeEnum::TYPE_OBJECT) {
            // A DTO, or a free-form object (e.g. "object" or stdClass type): any properties.
            $fieldSchema = $this->getEndpointResponseSchema($field->children ?? 'object');

            return $field->isNullable
                ? ['nullable' => true, 'allOf' => [$fieldSchema]]
                : $fieldSchema;
        }

        return $this->getEndpointParameterSchema($field);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getEndpointParameterSchema(
        OpenApiEndpointResponseParameterDTO|OpenApiEndpointRequestParameterDTO $parameter
    ): array {
        $result = [
            'type'     => $this->getEndpointParameterType($parameter),
            'nullable' => $parameter->isNullable
        ];

        if ($parameter->format !== '') {
            $result['format'] = $parameter->format;
        }

        if ($parameter->description !== '') {
            $result['description'] = $parameter->description;
        }

        if ($parameter->enum !== []) {
            $result['enum'] = $parameter->enum;
        }

        if ($this->isFileParameter($parameter)) {
            $result['format'] = "binary";
        }

        foreach (
            [
                'default'   => $parameter->default,
                'example'   => $parameter->example,
                'minimum'   => $parameter->minimum,
                'maximum'   => $parameter->maximum,
                'minLength' => $parameter->minLength,
                'maxLength' => $parameter->maxLength,
            ] as $fieldName => $value
        ) {
            if ($value !== null) {
                $result[$fieldName] = $value;
            }
        }

        if ($parameter instanceof OpenApiEndpointRequestParameterDTO) {
            $result['allowEmptyValue'] = $parameter->allowEmptyValue;
            $result['required'] = $parameter->isRequired;
        }

        return $result;
    }

    protected function getEndpointParameterType(
        OpenApiEndpointResponseParameterDTO|OpenApiEndpointRequestParameterDTO $parameter
    ): OpenApiEndpointParameterTypeEnum {
        return $this->isFileParameter($parameter)
            ? OpenApiEndpointParameterTypeEnum::TYPE_STRING
            : $parameter->type;
    }

    protected function isFileParameter(
        OpenApiEndpointResponseParameterDTO|OpenApiEndpointRequestParameterDTO $parameter
    ): bool {
        return $parameter instanceof OpenApiEndpointRequestParameterDTO && $parameter->source === 'file';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getUnauthorizedResponse(): array {
        return $this->getErrorResponse("Unauthorized");
    }

    /**
     * @return array<string, mixed>
     */
    protected function getInternalErrorResponse(): array {
        return $this->getErrorResponse("Internal server error");
    }

    /**
     * @return array<string, mixed>
     */
    protected function getErrorResponse(string $description): array {
        return [
            'description' => $description,
            'content'     => [
                'application/json' => [
                    'schema' => [
                        '$ref' => '#/components/schemas/Error',
                    ]
                ]
            ]
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function getErrorSchema(): array {
        return [
            'Error'        => [
                'type'       => "object",
                'properties' => [
                    'errors'    => [
                        'type'  => "array",
                        'items' => [
                            '$ref' => '#/components/schemas/ErrorMessage',
                        ]
                    ],
                    'requestId' => [
                        'type' => "string"
                    ]
                ]
            ],
            'ErrorMessage' => [
                'type'       => "object",
                'properties' => [
                    'code'    => [
                        'type' => "integer"
                    ],
                    'message' => [
                        'type' => "string"
                    ]
                ]
            ]
        ];
    }
}
