<?php

declare(strict_types=1);

namespace ApiPlatform\Attribute\Docs;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Group {
    public function __construct(
        public string $name
    ) {}
}