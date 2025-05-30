<?php

declare(strict_types=1);

namespace ApiPlatform\Exception;

use Throwable;

class AuthorizationRequiredException extends ApiException {
    public function __construct(?Throwable $previous = null) {
        parent::__construct(["Not authorized"], 401, $previous);
    }
}