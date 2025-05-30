<?php

declare(strict_types=1);

namespace ApiPlatform\DTO;

readonly class ApiMethodParameterDTO {
    public function __construct(
        public string $source,
        public string $name,
        public string $argumentName,
        public bool   $hasDefaultValue,
        public mixed  $defaultValue
    ) {}
}