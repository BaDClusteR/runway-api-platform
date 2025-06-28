<?php

namespace ApiPlatform\Attribute\Docs;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
readonly class Throws
{
    public function __construct(
        public int $code,
        public string $description
    ) {
    }
}