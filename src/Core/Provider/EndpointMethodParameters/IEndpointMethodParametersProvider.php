<?php

namespace ApiPlatform\Core\Provider\EndpointMethodParameters;

use ApiPlatform\DTO\ApiEndpointDTO;
use ApiPlatform\DTO\ApiEndpointMethodParameterDTO;
use ApiPlatform\DTO\ApiRequestDTO;
use ApiPlatform\Exception\InternalErrorException;
use ApiPlatform\Exception\RequiredParameterNotProvidedException;

interface IEndpointMethodParametersProvider {
    /**
     * @return ApiEndpointMethodParameterDTO[]
     *
     * @throws InternalErrorException
     * @throws RequiredParameterNotProvidedException
     */
    public function getEndpointMethodParameters(ApiEndpointDTO $endpoint, ?ApiRequestDTO $request = null): array;
}