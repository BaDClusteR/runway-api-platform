<?php

declare(strict_types=1);

namespace ApiPlatform\Attribute\Assert;

use ApiPlatform\DTO\ApiParameterValidationResultDTO;

abstract class AAssertion implements IAssertion {
    protected array $errors = [];

    protected function addError(string $errorText): void {
        $this->errors[] = $errorText;
    }

    protected function getResultDTO(): ApiParameterValidationResultDTO {
        return new ApiParameterValidationResultDTO(
            isSuccessful: empty($this->errors),
            errors: $this->errors
        );
    }
}