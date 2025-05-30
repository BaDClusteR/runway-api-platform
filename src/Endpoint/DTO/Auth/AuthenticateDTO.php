<?php

declare(strict_types=1);

namespace ApiPlatform\Endpoint\DTO\Auth;

use ApiPlatform\Attribute\Docs;

#[Docs\Response("Authentication info")]
readonly class AuthenticateDTO {
    public function __construct(
        #[Docs\Property(description: "Generated token", example: "MyAwesomeNewAuthToken")]
        public string $token,

        #[Docs\Property(
            description: "The date and time until the token is active",
            format: "date-time",
            example: "2030-01-15T02:12:28+02:00"
        )]
        public string $valid_until
    ) {}
}