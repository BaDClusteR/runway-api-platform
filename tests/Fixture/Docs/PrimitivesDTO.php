<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Fixture\Docs;

use ApiPlatform\Attribute\Docs;

/**
 * The arrays of the primitive and the free-form values.
 */
class PrimitivesDTO {
    /** @var mixed Without a type: any value. */
    public $any = null;

    /**
     * @param float[]  $ratios
     * @param bool[]   $flags
     * @param mixed[]  $values
     * @param string[] $names
     */
    public function __construct(
        #[Docs\Property(childrenType: 'float')]
        public array $ratios = [],
        #[Docs\Property(childrenType: 'bool')]
        public array $flags = [],
        #[Docs\Property(childrenType: 'mixed')]
        public array $values = [],
        // The case of the types.
        #[Docs\Property(childrenType: 'String')]
        public array $names = []
    ) {}
}
