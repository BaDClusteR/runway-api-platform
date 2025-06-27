<?php

declare(strict_types=1);

namespace ApiPlatform\Attribute\Docs;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Property {
    public function __construct(
        public string $description = '',
        public string $format = '',
        public array  $enum = [],
        public mixed  $example = null,
        public string $childrenType = ''
    ) {}
}