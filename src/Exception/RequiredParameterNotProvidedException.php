<?php

declare(strict_types=1);

namespace ApiPlatform\Exception;

use Throwable;

class RequiredParameterNotProvidedException extends ApiException {
    public function __construct(
        protected string $parameterName,
        ?Throwable       $previous = null
    ) {
        parent::__construct(["Required parameter {$this->parameterName} is not provided."], 400, $previous);
    }
}