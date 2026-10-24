<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Fixture\Docs;

use ApiPlatform\Attribute as Api;

class UnionReturnEndpoints {
    #[Api\Get('union')]
    public function get(): PrimitivesDTO|TagDTO {
        return new PrimitivesDTO();
    }
}
