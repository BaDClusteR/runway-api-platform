<?php

declare(strict_types=1);

namespace ApiPlatform\DTO;

readonly class ApiEndpointParameterDTO {
    public function __construct(
        public string $name,
        public array  $assertions,
    ) {}
}