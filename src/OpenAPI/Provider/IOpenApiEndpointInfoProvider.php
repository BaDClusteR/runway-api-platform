<?php

namespace ApiPlatform\OpenAPI\Provider;

use ApiPlatform\DTO\ApiEndpointDTO;
use ApiPlatform\OpenAPI\DTO\OpenApiEndpointInfoDTO;

interface IOpenApiEndpointInfoProvider {
    public function getEndpointInfo(ApiEndpointDTO $endpoint): OpenApiEndpointInfoDTO;
}