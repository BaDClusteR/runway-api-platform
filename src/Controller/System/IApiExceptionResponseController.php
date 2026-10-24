<?php

namespace ApiPlatform\Controller\System;

use Runway\Request\Response;
use Throwable;

interface IApiExceptionResponseController {
    /**
     * The JSON error: the code of an API exception (4xx, 5xx), 500 for the others. The messages of the internal errors
     * (5xx) and the traces are shown only in the debug mode.
     */
    public function getResponse(Throwable $exception): Response;
}
