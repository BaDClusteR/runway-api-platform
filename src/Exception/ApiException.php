<?php

declare(strict_types=1);

namespace ApiPlatform\Exception;

use Runway\Exception\Exception;
use Throwable;

class ApiException extends Exception {
    /**
     * @param string[] $errors
     */
    public function __construct(
        public readonly array $errors,
        int                   $code = 0,
        ?Throwable            $previous = null
    ) {
        parent::__construct(
            implode("\n", $this->errors),
            $code,
            $previous
        );
    }
}