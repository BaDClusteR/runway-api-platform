<?php

declare(strict_types=1);

namespace ApiPlatform\DTO;

readonly class ApiMethodParameterDTO {
    /**
     * @param string $source One of the ApiPlatform\Attribute\Parameter::SOURCE_* constants, or an empty string if the
     *                       argument is not taken from the request (it gets its default value).
     * @param string $name   The name in the request, e.g. of the path placeholder.
     */
    public function __construct(
        public string $source,
        public string $name,
        public string $argumentName,
        public bool   $hasDefaultValue,
        public mixed  $defaultValue
    ) {}
}
