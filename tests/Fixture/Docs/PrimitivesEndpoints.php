<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Fixture\Docs;

use ApiPlatform\Attribute as Api;

class PrimitivesEndpoints {
    #[Api\Get('primitives', public: true)]
    public function get(): PrimitivesDTO {
        return new PrimitivesDTO();
    }
}
