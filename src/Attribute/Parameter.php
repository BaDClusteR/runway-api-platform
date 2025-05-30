<?php

declare(strict_types=1);

namespace ApiPlatform\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
readonly class Parameter {
    public function __construct(
        public string  $source,
        public ?string $name = null
    ) {}
}