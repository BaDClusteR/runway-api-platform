<?php

declare(strict_types=1);

namespace ApiPlatform\Endpoint;

use ApiPlatform\Attribute as API;
use ApiPlatform\Endpoint\DTO\PingDTO;

class Ping {
    #[API\Get("ping")]
    public function ping(): PingDTO {
        return new PingDTO();
    }
}