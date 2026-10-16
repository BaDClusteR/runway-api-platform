<?php

declare(strict_types=1);

namespace ApiPlatform\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
readonly class Body extends Parameter {
    public function __construct(?string $name = null) {
        parent::__construct(self::SOURCE_BODY, $name);
    }
}
