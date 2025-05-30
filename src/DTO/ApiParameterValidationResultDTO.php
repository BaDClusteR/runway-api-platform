<?php

declare(strict_types=1);

namespace ApiPlatform\DTO;

readonly class ApiParameterValidationResultDTO {
    /**
     * @param string[] $errors
     */
    public function __construct(
        public bool  $isSuccessful,
        public array $errors
    ) {}
}