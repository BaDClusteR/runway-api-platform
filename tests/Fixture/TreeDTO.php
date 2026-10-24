<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Fixture;

use ApiPlatform\Attribute\Docs;

#[Docs\Response('A tree of free-form nodes')]
readonly class TreeDTO {
    /**
     * @param array<mixed> $tree
     */
    public function __construct(
        #[Docs\Property('Folders and items', childrenType: 'object')]
        public array   $tree,
        #[Docs\Property('Any keys')]
        public ?object $meta = null
    ) {}
}
