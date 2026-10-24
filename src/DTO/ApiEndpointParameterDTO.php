<?php

declare(strict_types=1);

namespace ApiPlatform\DTO;

use ApiPlatform\Attribute\Assert\IAssertion;

readonly class ApiEndpointParameterDTO {
    /**
     * @param IAssertion[] $assertions
     */
    public function __construct(
        public string $name,
        public array  $assertions,
    ) {}
}
