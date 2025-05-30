<?php

namespace ApiPlatform\Core\Provider\EndpointMethodParameterValue;

use ApiPlatform\DTO\ApiMethodParameterDTO;
use ApiPlatform\DTO\ApiRequestDTO;
use ApiPlatform\Exception\RequiredParameterNotProvidedException;

interface IEndpointMethodParameterValueProvider {
    /**
     * @throws RequiredParameterNotProvidedException
     */
    public function getEndpointMethodParameterValue(ApiMethodParameterDTO $parameter, ApiRequestDTO $request): mixed;
}