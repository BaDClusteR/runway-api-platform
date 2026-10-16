<?php

namespace ApiPlatform\Controller;

use Runway\Request\Response;

interface IApiController {
    /**
     * Calls the endpoint of the route found by the router.
     *
     * @param string ...$pathParameters Placeholder name => value.
     */
    public function handle(string ...$pathParameters): Response;
}
