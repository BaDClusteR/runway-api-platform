<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Fixture\Docs;

use ApiPlatform\Attribute as Api;
use ApiPlatform\Attribute\Assert;
use ApiPlatform\Attribute\Docs;
use ApiPlatform\DTO\ApiEndpointArgumentFileDTO;
use ApiPlatform\Endpoint\DTO\Auth\DoneDTO;

/**
 * The endpoints of the OpenAPI tests: the docs attributes and the assertions.
 */
#[Docs\Group('Notes')]
#[Api\Prefix('notes')]
class DocsEndpoints {
    #[Api\Get('{noteId}', public: true)]
    #[Docs\Endpoint('Get a note', 'The note with its tags', 'getNote')]
    #[Docs\Throws(code: 404, description: 'No such note')]
    #[Docs\Throws(code: 403, description: 'Not yours')]
    public function get(
        int $noteId,
        #[Api\Query]
        #[Docs\Argument(format: 'date', enum: ['a', 'b'], example: 'a', description: 'The view')]
        #[Assert\NotEmpty]
        string $view = 'a',
        #[Api\Query]
        #[Assert\Range(min: 0.5, max: 10)]
        ?float $zoom = null,
        bool $isInternal = false
    ): NoteDTO {
        return new NoteDTO($noteId, 'Title', [], null, []);
    }

    #[Api\Post]
    #[Docs\Endpoint('Create a note')]
    public function create(
        #[Api\Body] #[Assert\Length(minLength: 1, maxLength: 100)] string $title,
        #[Api\File] ApiEndpointArgumentFileDTO $attachment
    ): NoteDTO {
        return new NoteDTO(1, $title, [], null, []);
    }

    #[Api\Delete('{noteId}')]
    public function delete(int $noteId): DoneDTO {
        return new DoneDTO();
    }

    /**
     * @param string[] $tags
     */
    #[Api\Patch('{noteId}')]
    public function update(int $noteId, #[Api\Body] array $tags = []): DoneDTO {
        return new DoneDTO();
    }
}
