<?php

declare(strict_types=1);

namespace ApiPlatform\Attribute\Assert;

use ApiPlatform\DTO\ApiEndpointMethodParameterDTO;
use ApiPlatform\DTO\ApiParameterValidationResultDTO;

interface IAssertion {
    public function assert(ApiEndpointMethodParameterDTO $parameter): ApiParameterValidationResultDTO;
}