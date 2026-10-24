<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Fixture\Invalid;

use ApiPlatform\Attribute as Api;

class ArgumentWithoutSource {
    #[Api\Get('chats')]
    public function run(int $limit): void {}
}
