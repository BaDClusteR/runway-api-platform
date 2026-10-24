<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Fixture;

use ApiPlatform\Attribute as Api;

#[Api\Prefix('document-folders')]
class DocumentFolderEndpoints extends AFolderEndpoints {
}
