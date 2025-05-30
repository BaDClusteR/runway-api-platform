<?php

declare(strict_types=1);

namespace ApiPlatform\Exception;

use Throwable;

class UnauthorizedException extends ApiException {
    public function __construct(
        string     $message = "API token is invalid or not provided.",
        ?Throwable $previous = null
    ) {
        parent::__construct([$message], 401, $previous);
    }
}