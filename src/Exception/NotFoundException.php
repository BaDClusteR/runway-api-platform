<?php

declare(strict_types=1);

namespace ApiPlatform\Exception;

use Throwable;

/**
 * The requested entity does not exist, e.g. a chat with the ID from the path.
 */
class NotFoundException extends ApiException {
    public function __construct(
        array|string $errors,
        ?Throwable   $previous = null
    ) {
        parent::__construct((array)$errors, 404, $previous);
    }
}
