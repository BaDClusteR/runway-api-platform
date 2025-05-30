<?php

declare(strict_types=1);

namespace ApiPlatform\Exception;

use Throwable;

class BadRequestException extends ApiException {
    public function __construct(
        array      $errors,
        ?Throwable $previous = null
    ) {
        parent::__construct($errors, 400, $previous);
    }
}