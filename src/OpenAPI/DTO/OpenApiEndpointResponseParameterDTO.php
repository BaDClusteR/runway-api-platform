<?php

declare(strict_types=1);

namespace ApiPlatform\OpenAPI\DTO;

use ApiPlatform\OpenAPI\Enum\OpenApiEndpointParameterTypeEnum;

readonly class OpenApiEndpointResponseParameterDTO
{
    /**
     * @param array<mixed>                                 $enum     The allowed values.
     * @param OpenApiEndpointResponseSchemaDTO|string|null $children The items of an array or the properties of an
     *                                                               object: a DTO schema or a PHP type name.
     */
    public function __construct(
        public string $name,
        public OpenApiEndpointParameterTypeEnum $type,
        public bool $isNullable,
        public string $description = '',
        public string $format = '',
        public mixed $default = null,
        public mixed $example = null,
        public array $enum = [],
        public int|float|null $minimum = null,
        public int|float|null $maximum = null,
        public ?int $minLength = null,
        public ?int $maxLength = null,
        public OpenApiEndpointResponseSchemaDTO|string|null $children = null
    ) {
    }
}
