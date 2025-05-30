<?php

declare(strict_types=1);

namespace ApiPlatform\Attribute\Docs;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
readonly class Endpoint {
    public function __construct(
        public string $title,
        public string $description = '',
        public string $operationId = ''
    ) {}
}