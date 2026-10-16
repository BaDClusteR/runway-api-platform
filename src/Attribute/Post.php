<?php

declare(strict_types=1);

namespace ApiPlatform\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
readonly class Post extends Endpoint {
    public function __construct(string $path = '', bool $public = false) {
        parent::__construct($path, 'POST', $public);
    }
}
