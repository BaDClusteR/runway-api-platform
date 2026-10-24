<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Fixture;

use ApiPlatform\Attribute as Api;

/**
 * A mistake found only when the endpoint is called: a service without the endpoint tag (not in the spec, see
 * tests/config/endpoints.yaml).
 */
class ScalarEndpoints {
    #[Api\Get('scalar')]
    public function scalar(): string {
        return 'not a DTO';
    }
}
