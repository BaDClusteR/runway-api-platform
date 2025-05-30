<?php

declare(strict_types=1);

namespace ApiPlatform\DTO;

readonly class ApiParameterDTO {
    public function __construct(
        public string $name,
        public mixed  $value
    ) {}
}