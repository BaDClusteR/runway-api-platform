<?php

declare(strict_types=1);

namespace ApiPlatform\Exception;

use Throwable;

class AuthFailedException extends UnauthorizedException {
    public function __construct(
        ?Throwable $previous = null
    ) {
        parent::__construct("Invalid credentials.", $previous);
    }
}