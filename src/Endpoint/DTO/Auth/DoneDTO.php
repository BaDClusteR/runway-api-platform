<?php

declare(strict_types=1);

namespace ApiPlatform\Endpoint\DTO\Auth;

use ApiPlatform\Attribute\Docs;

/**
 * No data: the response is "204 No Content".
 */
#[Docs\Response('Done')]
readonly class DoneDTO {
}
