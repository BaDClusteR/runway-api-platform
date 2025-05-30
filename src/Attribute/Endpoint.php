<?php

declare(strict_types=1);

namespace ApiPlatform\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
readonly class Endpoint {
    public function __construct(
        public string $path,
        public string $method,
        public bool   $public = false
    ) {}
}