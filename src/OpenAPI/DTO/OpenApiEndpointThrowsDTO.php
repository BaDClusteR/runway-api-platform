<?php

namespace ApiPlatform\OpenAPI\DTO;

readonly class OpenApiEndpointThrowsDTO
{
    public function __construct(
        public int $code,
        public string $description,
    ) {
    }
}