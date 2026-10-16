<?php

declare(strict_types=1);

namespace ApiPlatform\Attribute;

use Attribute;

/**
 * An API endpoint: the method handles the requests with this HTTP method and path. The path is a template relative to
 * the API prefix (and the class Prefix), e.g. "chats/{chatId}/chunks/{index}". The placeholders are passed to the
 * method arguments with the same names; an int / float / bool argument limits the placeholder to such values, another
 * requirement can be set inline: "{slug<[a-z-]+>}".
 *
 * Get, Post, Put, Patch and Delete are the shortcuts.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
readonly class Endpoint {
    public function __construct(
        public string $path,
        public string $method,
        public bool   $public = false
    ) {}
}
