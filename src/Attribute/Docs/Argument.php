<?php

declare(strict_types=1);

namespace ApiPlatform\Attribute\Docs;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Argument {
    /**
     * @param array<mixed> $enum The allowed values.
     */
    public function __construct(
        public string $format = '',
        public array  $enum = [],
        public mixed  $example = null,
        public string $description = ''
    ) {}
}