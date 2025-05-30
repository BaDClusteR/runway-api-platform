<?php

declare(strict_types=1);

namespace ApiPlatform\DTO;

readonly class ApiEndpointMethodParameterValidationResultDTO {
    /**
     * @param string[] $errors
     */
    public function __construct(
        public bool  $isValidated,
        public array $errors
    ) {}
}