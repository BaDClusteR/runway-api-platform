<?php

namespace ApiPlatform\Controller;

use Runway\Request\Response;

interface IApiRouter {
    public function route(
        string  $apiVersion,
        ?string $section = null,
        ?string $action = null,
        ?string $identifier = null
    ): Response;
}