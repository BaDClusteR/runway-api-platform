<?php

declare(strict_types=1);

namespace ApiPlatform\Exception;

use Throwable;

class BadRequestException extends ApiException {
    public function __construct(
        array|string $errors,
        ?Throwable   $previous = null
    ) {
        parent::__construct((array)$errors, 400, $previous);
    }
}