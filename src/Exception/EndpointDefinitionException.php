<?php

declare(strict_types=1);

namespace ApiPlatform\Exception;

/**
 * An endpoint is declared incorrectly, e.g. its path has a placeholder without an argument.
 */
class EndpointDefinitionException extends InternalErrorException {
}
