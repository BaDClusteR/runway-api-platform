<?php

namespace ApiPlatform\Core\Validator;

use ApiPlatform\DTO\ApiEndpointMethodParameterDTO;
use ApiPlatform\DTO\ApiEndpointMethodParameterValidationResultDTO;

interface IEndpointMethodParameterValidator {
    public function validate(ApiEndpointMethodParameterDTO $parameter): ApiEndpointMethodParameterValidationResultDTO;
}