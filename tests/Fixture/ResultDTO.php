<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Fixture;

use ApiPlatform\Attribute\Docs;

#[Docs\Response('The arguments of the endpoint')]
readonly class ResultDTO {
    /**
     * @param array<mixed> $result
     */
    public function __construct(
        #[Docs\Property('Argument name => value', childrenType: 'string')]
        public array $result
    ) {}
}
