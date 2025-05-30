<?php

declare(strict_types=1);

namespace ApiPlatform\DTO;

use ApiPlatform\Attribute\Assert\IAssertion;

readonly class ApiEndpointMethodParameterDTO {
    /**
     * @param IAssertion[] $assertions
     */
    public function __construct(
        public string $name,
        public string $argumentName,
        public string $type,
        public bool   $isNullable,
        public mixed  $value,
        public array  $assertions
    ) {}
}