<?php

declare(strict_types=1);

namespace ApiPlatform\Exception;

use Throwable;

class EndpointNotFoundException extends ApiException {
    public function __construct(
        protected string $endpoint,
        protected string $method,
        ?Throwable       $previous = null
    ) {
        parent::__construct(
            ["[$this->method] $this->endpoint is not valid API endpoint."],
            404,
            $previous
        );
    }

    public function getEndpoint(): string {
        return $this->endpoint;
    }

    public function getMethod(): string {
        return $this->method;
    }
}