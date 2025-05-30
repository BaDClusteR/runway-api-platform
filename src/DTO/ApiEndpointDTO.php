<?php

declare(strict_types=1);

namespace ApiPlatform\DTO;

readonly class ApiEndpointDTO {
    public function __construct(
        public string $path,
        public string $requestMethod,
        public string $class,
        public string $method,
        public bool   $isPublic
    ) {}
}