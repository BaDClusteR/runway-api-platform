<?php

namespace ApiPlatform\Controller\System;

use Runway\Request\Response;
use Throwable;

interface IApiExceptionResponseController {
    public function getResponse(Throwable $exception): Response;
}