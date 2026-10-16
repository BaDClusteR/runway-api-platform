<?php

declare(strict_types=1);

namespace ApiPlatform\Attribute;

use Attribute;

/**
 * Where the argument value comes from. Without it, the argument is a path placeholder with the same name, or gets its
 * default value.
 *
 * Path, Query, Body and File are the shortcuts.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
readonly class Parameter {
    public const string SOURCE_PATH = 'path';

    public const string SOURCE_QUERY = 'query';

    public const string SOURCE_BODY = 'body';

    public const string SOURCE_FILE = 'file';

    /**
     * @param string      $source One of the SOURCE_* constants.
     * @param string|null $name   The name in the request (e.g. of the placeholder), the argument name by default.
     */
    public function __construct(
        public string  $source,
        public ?string $name = null
    ) {}
}
