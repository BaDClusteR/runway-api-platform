<?php

declare(strict_types=1);

namespace ApiPlatform\Controller;

use ApiPlatform\Controller\System\IApiExceptionResponseController;
use Runway\Controller\ExceptionController;
use Runway\Request\Response;
use Throwable;

class ExceptionControllerDecorator extends ExceptionController {
    public function __construct(
        protected IApiExceptionResponseController $apiErrorResponseController
    ) {}

    public function run(Throwable $exception): Response {
        return $this->apiErrorResponseController->getResponse(
            $exception
        );
    }
}