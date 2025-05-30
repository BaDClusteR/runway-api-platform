<?php

declare(strict_types=1);

namespace ApiPlatform\OpenAPI\DTO;

readonly class OpenApiEndpointResponseSchemaDTO {
    /**
     * @param OpenApiEndpointResponseParameterDTO[] $schema
     */
    public function __construct(
        public array  $schema,
        public string $refName,
        public string $description = ''
    ) {}
}