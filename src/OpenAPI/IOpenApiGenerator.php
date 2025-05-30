<?php

declare(strict_types=1);

namespace ApiPlatform\OpenAPI;

interface IOpenApiGenerator {
    public function generateOpenApiSpec(): array;
}