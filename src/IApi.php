<?php

namespace ApiPlatform;

use ApiPlatform\DTO\ApiEndpointDTO;
use ApiPlatform\DTO\ApiRequestDTO;
use ApiPlatform\DTO\ApiResponseDTO;
use ApiPlatform\Exception\ApiException;

interface IApi {
    /**
     * Checks the access, collects and validates the arguments and calls the endpoint.
     *
     * @throws ApiException
     */
    public function execute(ApiEndpointDTO $endpoint, ApiRequestDTO $request): ApiResponseDTO;

    public function getVersion(): string;
}
