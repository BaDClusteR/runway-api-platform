<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Fixture\Docs;

use ApiPlatform\Attribute\Assert;
use ApiPlatform\Attribute\Docs;

#[Docs\Response('A note')]
readonly class NoteDTO {
    /**
     * @param TagDTO[] $tags
     * @param int[]    $likes
     */
    public function __construct(
        #[Docs\Property('The ID', example: 42)]
        #[Assert\Range(min: 1)]
        public int     $id,
        #[Docs\Property('The title', format: 'text', enum: ['Title'])]
        #[Assert\Length(maxLength: 100)]
        public string  $title,
        #[Docs\Property('The tags', childrenType: TagDTO::class)]
        public array   $tags,
        #[Docs\Property('The author')]
        public ?TagDTO $author,
        #[Docs\Property(childrenType: 'int')]
        public array   $likes,
        public bool    $isPinned = false
    ) {}
}
