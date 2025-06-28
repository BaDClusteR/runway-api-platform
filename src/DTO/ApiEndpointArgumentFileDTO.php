<?php

namespace ApiPlatform\DTO;

readonly class ApiEndpointArgumentFileDTO
{
    public function __construct(
        public string $name,
        public string $mimeType,
        public string $tmpName,
        public int $size
    ) {
    }
}