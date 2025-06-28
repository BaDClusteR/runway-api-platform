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
    protected array $paths = [];

    protected array $schemas = [];

    public function __construct(
        protected IEndpointsProvider           $endpointsProvider,
        protected IOpenApiEndpointInfoProvider $endpointInfoProvider,
        protected IApi                         $api,
        protected string                       $endpointPrefix,
        protected string                       $tokenHeader
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

    protected function getComponents(): array {
        ksort($this->schemas, SORT_STRING);

        return [
            'schemas'         => $this->schemas,
            'securitySchemes' => $this->getSecuritySchemes()
        ];
    }

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

    protected function getInfo(): array {
        return [
            'title'       => 'History API',
            'description' => 'API spec of the BC History',
            'version'     => $this->api->getVersion()
        ];
    }

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

            $endpointPath = $this->endpointPrefix . $endpoint->path;
            $this->paths[$endpointPath] = [
                ...($paths[$endpointPath] ?? []),
                ...$this->getEndpointPathInfo($endpoint, $endpointInfo)
            ];
        }
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
        return $endpoint->class !== Schema::class
            && $endpoint->method !== "generateOpenApiSchema";
    }

    /**
     * @throws InternalErrorException
     */
    protected function getEndpointPathInfo(ApiEndpointDTO $endpoint, OpenApiEndpointInfoDTO $endpointInfo): array {
        return [
            strtolower($endpoint->requestMethod) => $this->getEndpointInfo($endpointInfo)
        ];
    }

    /**
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

        if ($parameters = $this->getEndpointParameters($endpointInfo)) {
            $result['parameters'] = $parameters;
        }

        if ($bodyParameters = $this->getEndpointBodyParameters($endpointInfo)) {
            $result['requestBody'] = [
                'content' => [
                    $endpointInfo->responseMimeType => [
                        'schema' => [
                            'type'       => "object",
                            'properties' => $bodyParameters
                        ]
                    ]
                ]
            ];
        }

        $result['deprecated'] = $endpointInfo->isDeprecated;

        return $result;
    }

    protected function getEndpointBodyParameters(OpenApiEndpointInfoDTO $endpointInfo): array {
        $parameters = [];

        foreach ($this->getBodyParameters($endpointInfo) as $parameter) {
            $parameters[$parameter->name] = $this->getEndpointParameterSchema(
                $parameter
            );
        }

        return $parameters;
    }

    protected function getEndpointParameters(OpenApiEndpointInfoDTO $endpointInfo): array {
        $parameters = [];

        foreach ($this->getRequestParameters($endpointInfo) as $parameter) {
            $parameters[$parameter->name] = [
                ...$this->getEndpointParameterSchema(
                    $parameter
                ),
                'in' => $parameter->source
            ];
        }

        return $parameters;
    }

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
            $responses[(string)$throw->code] = $this->getErrorResponse($throw->description);
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
     * @throws InternalErrorException
     */
    protected function getSuccessfulResponse(OpenApiEndpointInfoDTO $endpointInfo): array {
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
     * @throws InternalErrorException
     */
    protected function getEndpointResponseSchema(OpenApiEndpointResponseSchemaDTO|string $schema): array {
        if (is_string($schema)) {
            return [
                'type' => $schema
            ];
        }

        if (empty($this->schemas[$schema->refName])) {
            $responseSchema = [];

            if ($schema->description) {
                $responseSchema['description'] = $schema->description;
            }

            if (empty($schema->schema)) {
                return $responseSchema;
            }

            foreach ($schema->schema as $field) {
                if ($field->type === OpenApiEndpointParameterTypeEnum::TYPE_ARRAY) {
                    if (!$field->children) {
                        throw new InternalErrorException(
                            "Error while generating schema for '$schema->refName': field '$field->name' is array, but the items type is not defined."
                        );
                    }

                    $responseSchema[$field->name] = [
                        'type'  => "array",
                        'items' => $this->getEndpointResponseSchema($field->children)
                    ];
                } elseif ($field->type === OpenApiEndpointParameterTypeEnum::TYPE_OBJECT) {
                    if (!$field->children) {
                        throw new InternalErrorException(
                            "Error while generating schema for '$schema->refName': field '$field->name' is object, but its type is not defined."
                        );
                    }

                    $responseSchema[$field->name] = [
                        'type'       => "object",
                        'properties' => $this->getEndpointResponseSchema($field->children)
                    ];
                } else {
                    $responseSchema[$field->name] = $this->getEndpointParameterSchema($field);
                }
            }

            $this->schemas[$schema->refName] = $responseSchema;
        }

        return [
            '$ref' => "#/components/schemas/$schema->refName"
        ];
    }

    protected function getEndpointParameterSchema(
        OpenApiEndpointResponseParameterDTO|OpenApiEndpointRequestParameterDTO $parameter
    ): array {
        $result = [
            'type'     => $this->getEndpointParameterType($parameter),
            'nullable' => $parameter->isNullable
        ];

        foreach (["format", "description", "enum"] as $field) {
            if (!empty($parameter->{$field})) {
                $result[$field] = $parameter->{$field};
            }
        }

        if (($parameter?->source ?? null) === "file") {
            $result['format'] = "binary";
        }

        foreach (
            ["default", "example", "minimum", "maximum", "minLength", "maxLength", "allowEmptyValue"]
            as $fieldName
        ) {
            if (isset($parameter->{$fieldName})) {
                $result[$fieldName] = $parameter->{$fieldName};
            }
        }

        if (isset($parameter->isRequired)) {
            $result["required"] = $parameter->isRequired;
        }

        return $result;
    }

    protected function getEndpointParameterType(
        OpenApiEndpointResponseParameterDTO|OpenApiEndpointRequestParameterDTO $parameter
    ): OpenApiEndpointParameterTypeEnum {
        return ($parameter?->source ?? null) === "file"
            ? OpenApiEndpointParameterTypeEnum::TYPE_STRING
            : $parameter->type;
    }

    protected function getUnauthorizedResponse(): array {
        return $this->getErrorResponse("Unauthorized");
    }

    protected function getInternalErrorResponse(): array {
        return $this->getErrorResponse("Internal server error");
    }

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