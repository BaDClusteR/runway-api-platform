<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Fixture\Invalid;

use ApiPlatform\Attribute as Api;

class PlaceholderWithoutArgument {
    #[Api\Get('chats/{chatId}/messages/{messageId}')]
    public function run(int $chatId): void {}
}
