<?php

declare(strict_types=1);

namespace ApiPlatform\Attribute\Assert;

use Attribute;
use ApiPlatform\DTO\ApiEndpointMethodParameterDTO;
use ApiPlatform\DTO\ApiParameterValidationResultDTO;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
class Length extends AAssertion {
    public function __construct(
        public readonly ?int $minLength = null,
        public readonly ?int $maxLength = null,
    ) {}

    public function assert(ApiEndpointMethodParameterDTO $parameter): ApiParameterValidationResultDTO {
        $value = (string)$parameter->value;
        $strLength = strlen($value);

        if ($this->minLength !== null && $strLength < $this->minLength) {
            $this->addError(
                "{$parameter->name} should be at least {$this->minLength} characters long, $strLength given."
            );
        }

        if ($this->maxLength !== null && $value > $this->maxLength) {
            $this->addError(
                "{$parameter->name} should be at most {$this->maxLength} characters long, $strLength given."
            );
        }

        return $this->getResultDTO();
    }
}