<?php

declare(strict_types=1);

namespace ApiPlatform\DTO;

readonly class ApiRequestDTO {
    public function __construct(
        public ?string $section,
        public ?string $subSection,
        public ?string $identifier,
        public string  $method,
        public array   $body
    ) {}
}