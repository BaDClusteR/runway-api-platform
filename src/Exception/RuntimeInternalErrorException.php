<?php

declare(strict_types=1);

namespace ApiPlatform\Exception;

use Throwable;

class RuntimeInternalErrorException extends ApiRuntimeException {
    public function __construct(
        string     $error,
        ?Throwable $previous = null
    ) {
        parent::__construct([$error], 500, $previous);
    }
}