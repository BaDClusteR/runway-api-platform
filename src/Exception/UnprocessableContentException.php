<?php

declare(strict_types=1);

namespace ApiPlatform\Exception;

use Throwable;

class UnprocessableContentException extends ApiException {
    public function __construct(
        string     $error,
        ?Throwable $previous = null
    ) {
        parent::__construct([$error], 422, $previous);
    }
}