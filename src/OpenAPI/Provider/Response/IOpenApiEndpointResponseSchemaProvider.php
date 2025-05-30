<?php

namespace ApiPlatform\OpenAPI\Provider\Response;

use ApiPlatform\DTO\ApiEndpointDTO;
use ApiPlatform\OpenAPI\DTO\OpenApiEndpointResponseSchemaDTO;

interface IOpenApiEndpointResponseSchemaProvider {
    public function getEndpointResponseSchema(ApiEndpointDTO $endpoint): OpenApiEndpointResponseSchemaDTO;
}