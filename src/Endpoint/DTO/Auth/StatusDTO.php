<?php

declare(strict_types=1);

namespace ApiPlatform\Endpoint\DTO\Auth;

use ApiPlatform\Attribute\Docs;

#[Docs\Response('Whether the client is logged in')]
readonly class StatusDTO {
    public function __construct(
        #[Docs\Property('False: every endpoint is public (e.g. the access is limited by the web server)')]
        public bool $isEnabled,
        #[Docs\Property('Always true without the authorization')]
        public bool $isAuthenticated
    ) {}
}
