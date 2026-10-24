<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Fixture\Docs;

use ApiPlatform\Attribute as Api;

class ArrayWithoutItemsEndpoints {
    #[Api\Get('items')]
    public function items(): ArrayWithoutItemsDTO {
        return new ArrayWithoutItemsDTO([]);
    }
}
