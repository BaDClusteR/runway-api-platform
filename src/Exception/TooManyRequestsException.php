<?php

declare(strict_types=1);

namespace ApiPlatform\Exception;

use Throwable;

/**
 * E.g. too many failed logins: the client should wait.
 */
class TooManyRequestsException extends ApiException {
    public function __construct(
        array|string $errors,
        ?Throwable   $previous = null
    ) {
        parent::__construct((array)$errors, 429, $previous);
    }
}
