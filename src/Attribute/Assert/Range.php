<?php

declare(strict_types=1);

namespace ApiPlatform\Attribute\Assert;

use Attribute;
use ApiPlatform\DTO\ApiEndpointMethodParameterDTO;
use ApiPlatform\DTO\ApiParameterValidationResultDTO;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
class Range extends AAssertion {
    public function __construct(
        public readonly int|float|null $min = null,
        public readonly int|float|null $max = null,
    ) {}

    public function assert(ApiEndpointMethodParameterDTO $parameter): ApiParameterValidationResultDTO {
        if (!is_numeric($parameter->value)) {
            $this->addError("{$parameter->name} should be a number.");
        } else {
            $value = (int)$parameter->value;

            if ($this->min !== null && $value < $this->min) {
                $this->addError("{$parameter->name} should be {$this->min} or more, $value given.");
            }

            if ($this->max !== null && $value > $this->max) {
                $this->addError("{$parameter->name} should be {$this->max} or less, $value given.");
            }
        }

        return $this->getResultDTO();
    }
}