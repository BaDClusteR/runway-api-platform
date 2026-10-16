<?php

declare(strict_types=1);

namespace ApiPlatform\Attribute;

use Attribute;

/**
 * The common start of the paths of all the class endpoints, e.g. "chats/{chatId}": then the endpoint path "messages"
 * means "chats/{chatId}/messages".
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Prefix {
    public function __construct(
        public string $path
    ) {}
}
