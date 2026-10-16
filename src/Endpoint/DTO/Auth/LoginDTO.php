<?php

declare(strict_types=1);

namespace ApiPlatform\Endpoint\DTO\Auth;

use ApiPlatform\Attribute\Docs;

#[Docs\Response('A new session')]
readonly class LoginDTO {
    public function __construct(
        #[Docs\Property(
            description: 'The session token for the token header, null if it is in the cookie',
            example: '9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08'
        )]
        public ?string $token,
        #[Docs\Property(description: 'Until when the session is valid', format: 'date-time', example: '2030-01-15T02:12:28+02:00')]
        public string  $validUntil
    ) {}
}
