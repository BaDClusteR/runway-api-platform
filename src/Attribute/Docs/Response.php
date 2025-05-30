<?php

declare(strict_types=1);

namespace ApiPlatform\Attribute\Docs;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Response {
    public function __construct(
        public string $description = ''
    ) {}
}