<?php

declare(strict_types=1);

namespace ApiPlatform\OpenAPI;

interface IOpenApiGenerator {
    /**
     * @return array<string, mixed> The OpenAPI spec of the endpoints, for JSON.
     */
    public function generateOpenApiSpec(): array;
}
