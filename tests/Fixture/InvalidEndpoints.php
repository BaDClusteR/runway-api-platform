<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Fixture;

use ApiPlatform\Attribute as Api;

/**
 * Every class is an endpoint declaration mistake for EndpointsProviderTest.
 */
class PlaceholderWithoutArgument {
    #[Api\Get('chats/{chatId}/messages/{messageId}')]
    public function run(int $chatId): void {}
}

class PathArgumentWithoutPlaceholder {
    #[Api\Get('chats/{chatId}')]
    public function run(int $chatId, #[Api\Path] int $messageId): void {}
}

class ArgumentWithoutSource {
    #[Api\Get('chats')]
    public function run(int $limit): void {}
}

class InvalidPath {
    #[Api\Get('chats/{chatId')]
    public function run(int $chatId): void {}
}

class InvalidSource {
    #[Api\Get('chats')]
    public function run(#[Api\Parameter('cookie')] string $session): void {}
}
