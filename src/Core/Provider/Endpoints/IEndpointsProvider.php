<?php

namespace ApiPlatform\Core\Provider\Endpoints;

use ApiPlatform\DTO\ApiEndpointDTO;

interface IEndpointsProvider {
    /**
     * @return ApiEndpointDTO[]
     */
    public function getApiEndpoints(): array;
}