<?php

declare(strict_types=1);

namespace ApiPlatform\Core\Validator;

use ApiPlatform\DTO\ApiEndpointArgumentFileDTO;
use ApiPlatform\DTO\ApiEndpointMethodParameterDTO;
use ApiPlatform\DTO\ApiEndpointMethodParameterValidationResultDTO;
use ApiPlatform\Exception\InternalErrorException;

class EndpointMethodParameterValidator implements IEndpointMethodParameterValidator {
    /**
     * @var string[]
     */
    protected array $errors = [];

    /**
     * @throws InternalErrorException
     */
    public function validate(ApiEndpointMethodParameterDTO $parameter): ApiEndpointMethodParameterValidationResultDTO {
        $this->errors = [];

        $this->doValidate($parameter);

        return new ApiEndpointMethodParameterValidationResultDTO(
            isValidated: empty($this->errors),
            errors: $this->errors
        );
    }

    /**
     * @throws InternalErrorException
     */
    protected function doValidate(ApiEndpointMethodParameterDTO $parameter): void {
        $allowedParameterTypes = $this->getAllowedParameterTypes();

        if (!in_array($parameter->type, $allowedParameterTypes, true)) {
            throw new InternalErrorException(
                "{$parameter->argumentName} has incorrect type '{$parameter->type}'. Allowed types are "
                . implode(', ', $allowedParameterTypes) . "."
            );
        }

        if ($parameter->isNullable && $parameter->value === null) {
            return;
        }

        if (
            !is_numeric($parameter->value)
            && in_array($parameter->type, $this->getNumericParameterTypes(), true)
        ) {
            $this->addError("{$parameter->name} is not a number.");
        } elseif ($parameter->type === "int" && (float)$parameter->value !== round((float)$parameter->value)) {
            $value = (float)$parameter->value;
            $this->addError("{$parameter->name} is expected to be integer, float given ($value).");
        }

        if (
            !is_scalar($parameter->value)
            && in_array($parameter->type, $this->getStringParameterTypes(), true)
        ) {
            $this->addError("{$parameter->name} is not a string.");
        }

        if (
            !is_array($parameter->value)
            && in_array($parameter->type, $this->getArrayParameterTypes(), true)
        ) {
            $this->addError("{$parameter->name} is not an array.");
        }

        foreach ($parameter->assertions as $assertion) {
            $validationResult = $assertion->assert($parameter);

            if (!$validationResult->isSuccessful) {
                $this->addErrors($validationResult->errors);
            }
        }
    }

    /**
     * @return string[]
     */
    protected function getAllowedParameterTypes(): array {
        return [
            ...$this->getNumericParameterTypes(),
            ...$this->getStringParameterTypes(),
            ...$this->getArrayParameterTypes(),
            ApiEndpointArgumentFileDTO::class
        ];
    }

    protected function getNumericParameterTypes(): array {
        return ["int", "float"];
    }

    protected function getStringParameterTypes(): array {
        return ["string"];
    }

    protected function getArrayParameterTypes(): array {
        return ["array"];
    }

    protected function addError(string $error): void {
        $this->errors[] = $error;
    }

    protected function addErrors(array $errors): void {
        $this->errors = [...$this->errors, ...$errors];
    }
}