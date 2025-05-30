<?php

declare(strict_types=1);

namespace ApiPlatform\Attribute\Assert;

use Attribute;
use ApiPlatform\DTO\ApiEndpointMethodParameterDTO;
use ApiPlatform\DTO\ApiParameterValidationResultDTO;

#[Attribute(Attribute::TARGET_PARAMETER)]
class NotEmpty extends AAssertion {
    public function assert(ApiEndpointMethodParameterDTO $parameter): ApiParameterValidationResultDTO {
        if ($parameter->value === null || $parameter->value === '') {
            $this->addError("{$parameter->name} cannot be empty.");
        }

        return $this->getResultDTO();
    }
}