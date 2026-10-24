<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Fixture\Invalid;

use ApiPlatform\Attribute as Api;

class InvalidPath {
    #[Api\Get('chats/{chatId')]
    public function run(int $chatId): void {}
}
