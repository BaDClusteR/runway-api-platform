<?php

declare(strict_types=1);

namespace ApiPlatform\OpenAPI\DTO;

readonly class OpenApiEndpointInfoDTO {
    /**
     * @param OpenApiEndpointRequestParameterDTO[] $arguments
     */
    public function __construct(
        public string                           $group,
        public string                           $title,
        public string                           $description,
        public string                           $operationId,
        public array                            $arguments,
        public bool                             $isPublic,
        public OpenApiEndpointResponseSchemaDTO $responseSchema,
        public bool                             $isDeprecated = false
    ) {}
}