<?php

declare(strict_types=1);

namespace ApiPlatform\Exception;

use Throwable;

class MethodNotAllowedException extends ApiException {
    /**
     * @param string[] $allowedMethods
     */
    public function __construct(
        public readonly string $endpoint,
        public readonly string $method,
        public readonly array  $allowedMethods,
        ?Throwable             $previous = null
    ) {
        parent::__construct(
            ["[$this->method] $this->endpoint: the method is not allowed, allowed methods: "
             . implode(', ', $this->allowedMethods) . '.'],
            405,
            $previous
        );
    }
}
