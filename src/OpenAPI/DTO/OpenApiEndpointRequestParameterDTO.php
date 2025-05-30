<?php

declare(strict_types=1);

namespace ApiPlatform\OpenAPI\DTO;

use ApiPlatform\OpenAPI\Enum\OpenApiEndpointParameterTypeEnum;

readonly class OpenApiEndpointRequestParameterDTO {
    public function __construct(
        public string                           $name,
        public OpenApiEndpointParameterTypeEnum $type,
        public string                           $source,
        public bool                             $isRequired,
        public bool                             $isNullable,
        public string                           $description = '',
        public string                           $format = '',
        public mixed                            $default = null,
        public mixed                            $example = null,
        public bool                             $allowEmptyValue = true,
        public array                            $enum = [],
        public ?int                             $minimum = null,
        public ?int                             $maximum = null,
        public ?int                             $minLength = null,
        public ?int                             $maxLength = null,
    ) {}
}