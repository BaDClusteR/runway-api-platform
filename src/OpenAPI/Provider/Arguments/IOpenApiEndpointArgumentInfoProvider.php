<?php

namespace ApiPlatform\OpenAPI\Provider\Arguments;

use ApiPlatform\DTO\ApiEndpointDTO;
use ApiPlatform\Exception\InternalErrorException;
use ApiPlatform\OpenAPI\DTO\OpenApiEndpointRequestParameterDTO;
use ReflectionException;

interface IOpenApiEndpointArgumentInfoProvider {
    /**
     * @throws ReflectionException
     * @throws InternalErrorException
     */
    public function getEndpointArgumentInfo(
        ApiEndpointDTO $endpoint,
        string         $argumentName
    ): OpenApiEndpointRequestParameterDTO;
}