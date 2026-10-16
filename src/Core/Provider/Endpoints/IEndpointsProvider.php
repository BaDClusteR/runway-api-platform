<?php

namespace ApiPlatform\Core\Provider\Endpoints;

use ApiPlatform\DTO\ApiEndpointDTO;
use ApiPlatform\Exception\EndpointDefinitionException;

interface IEndpointsProvider {
    /**
     * @return ApiEndpointDTO[]
     *
     * @throws EndpointDefinitionException
     */
    public function getApiEndpoints(): array;

    /**
     * @throws EndpointDefinitionException
     */
    public function getEndpointByRouteName(string $routeName): ?ApiEndpointDTO;
}
