<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Fixture;

use ApiPlatform\Attribute as Api;

#[Api\Prefix('contact-folders')]
class ContactFolderEndpoints extends AFolderEndpoints {
}
