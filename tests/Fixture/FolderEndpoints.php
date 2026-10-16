<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Fixture;

use ApiPlatform\Attribute as Api;

/**
 * The endpoints shared by several classes: every subclass has its own prefix.
 */
abstract class AFolderEndpoints {
    #[Api\Put('{folderId}/name')]
    public function rename(int $folderId, #[Api\Body] string $name): ResultDTO {
        return new ResultDTO(['folderId' => $folderId, 'name' => $name]);
    }
}

#[Api\Prefix('contact-folders')]
class ContactFolderEndpoints extends AFolderEndpoints {
}

#[Api\Prefix('document-folders')]
class DocumentFolderEndpoints extends AFolderEndpoints {
}
