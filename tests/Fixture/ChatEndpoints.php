<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Fixture;

use ApiPlatform\Attribute as Api;

/**
 * Endpoints for the tests: they return their arguments.
 */
#[Api\Prefix('chats/{chatId}')]
class ChatEndpoints {
    #[Api\Get('', public: true)]
    public function get(int $chatId): ResultDTO {
        return new ResultDTO(['chatId' => $chatId]);
    }

    #[Api\Get('chunks/{index}', public: true)]
    public function chunk(int $chatId, int $index): ResultDTO {
        return new ResultDTO(['chatId' => $chatId, 'index' => $index]);
    }

    #[Api\Get('messages', public: true)]
    public function messages(
        int                  $chatId,
        #[Api\Query] ?int    $number = null,
        #[Api\Query] string  $direction = 'before',
        bool                 $isInternal = false
    ): ResultDTO {
        return new ResultDTO([
            'chatId'     => $chatId,
            'number'     => $number,
            'direction'  => $direction,
            'isInternal' => $isInternal,
        ]);
    }

    #[Api\Get('messages/latest', public: true)]
    public function latest(int $chatId): ResultDTO {
        return new ResultDTO(['chatId' => $chatId, 'latest' => true]);
    }

    /**
     * @param array<mixed> $meta
     */
    #[Api\Patch('messages/{messageId}/meta', public: true)]
    public function updateMeta(int $chatId, int $messageId, #[Api\Body] array $meta): ResultDTO {
        return new ResultDTO(['chatId' => $chatId, 'messageId' => $messageId, 'meta' => $meta]);
    }

    #[Api\Get('files/{name}', public: true)]
    #[Api\Get('attachments/{name}', public: true)]
    public function file(int $chatId, #[Api\Path('name')] string $filename): ResultDTO {
        return new ResultDTO(['chatId' => $chatId, 'filename' => $filename]);
    }

    #[Api\Get('years/{year<\d{4}>}', public: true)]
    public function year(int $chatId, string $year): ResultDTO {
        return new ResultDTO(['chatId' => $chatId, 'year' => $year]);
    }

    #[Api\Get('flags', public: true)]
    public function flags(int $chatId, #[Api\Query] bool $pinned = false): ResultDTO {
        return new ResultDTO(['chatId' => $chatId, 'pinned' => $pinned]);
    }

    #[Api\Delete('files', public: true)]
    public function deleteFile(int $chatId, #[Api\Query] string $path): ResultDTO {
        return new ResultDTO(['chatId' => $chatId, 'path' => $path]);
    }

    #[Api\Post('chunks', public: true)]
    public function appendChunk(int $chatId, #[Api\Query] int $offset): ResultDTO {
        return new ResultDTO(['chatId' => $chatId, 'offset' => $offset]);
    }

    #[Api\Get('tree', public: true)]
    public function tree(int $chatId): TreeDTO {
        return new TreeDTO([['type' => 'folder', 'children' => []]]);
    }

    #[Api\Get('private')]
    public function private(int $chatId): ResultDTO {
        return new ResultDTO(['chatId' => $chatId]);
    }
}
