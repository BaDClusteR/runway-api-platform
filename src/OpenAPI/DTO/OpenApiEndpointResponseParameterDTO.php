<?php

declare(strict_types=1);

namespace ApiPlatform\OpenAPI\DTO;

use ApiPlatform\OpenAPI\Enum\OpenApiEndpointParameterTypeEnum;

readonly class OpenApiEndpointResponseParameterDTO
{
    public function __construct(
        public string $name,
        public OpenApiEndpointParameterTypeEnum $type,
        public bool $isNullable,
        public string $description = '',
        public string $format = '',
        public mixed $default = null,
        public mixed $example = null,
        public array $enum = [],
        public ?int $minimum = null,
        public ?int $maximum = null,
        public ?int $minLength = null,
        public ?int $maxLength = null,
        public OpenApiEndpointResponseSchemaDTO|string|null $children = null
    ) {
    }
}