<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Fixture\Invalid;

use ApiPlatform\Attribute as Api;

class InvalidSource {
    #[Api\Get('chats')]
    public function run(#[Api\Parameter('cookie')] string $session): void {}
}
