<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Fixture;

use ApiPlatform\Attribute as Api;
use ApiPlatform\Attribute\Assert;
use ApiPlatform\DTO\ApiEndpointArgumentFileDTO;

/**
 * The corner cases of the declarations: the slashes of the paths, the types of the placeholders, the method case.
 */
#[Api\Prefix('/misc/')]
class MiscEndpoints {
    #[Api\Endpoint('/points/{x}/{y}/{visible}/', 'post', true)]
    public function point(float $x, float $y, bool $visible): ResultDTO {
        return new ResultDTO(['x' => $x, 'y' => $y, 'visible' => $visible]);
    }

    #[Api\Get('items/{id<-?\d+>}', public: true)]
    public function item(int $id): ResultDTO {
        return new ResultDTO(['id' => $id]);
    }

    #[Api\Get('mixed/{value}', public: true)]
    public function mixed(int|string $value): ResultDTO {
        return new ResultDTO(['value' => $value]);
    }

    #[Api\Post('validated', public: true)]
    public function validated(
        #[Api\Body] #[Assert\Length(maxLength: 3)] #[Assert\NotEmpty] string $code,
        #[Api\Query] #[Assert\Range(min: 1, max: 10)] ?int $count = null
    ): ResultDTO {
        return new ResultDTO(['code' => $code, 'count' => $count]);
    }

    #[Api\Post('body', public: true)]
    public function body(#[Api\Body] string $text, #[Api\Body] bool $flag, #[Api\Body] float $ratio): ResultDTO {
        return new ResultDTO(['text' => $text, 'flag' => $flag, 'ratio' => $ratio]);
    }

    #[Api\Post('upload', public: true)]
    public function upload(#[Api\File] ApiEndpointArgumentFileDTO $file, #[Api\File] ?ApiEndpointArgumentFileDTO $preview = null): ResultDTO {
        return new ResultDTO(['name' => $file->name, 'preview' => $preview?->name]);
    }

    public function notEndpoint(): void {}

    #[Api\Get('protected')]
    protected function protectedMethod(): void {}
}
