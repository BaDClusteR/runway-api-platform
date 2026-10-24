<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Fixture\Docs;

readonly class ArrayWithoutItemsDTO {
    /**
     * @param array<mixed> $items
     */
    public function __construct(
        public array $items
    ) {}
}
