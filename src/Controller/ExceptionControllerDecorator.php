<?php

declare(strict_types=1);

namespace ApiPlatform\Controller;

use ApiPlatform\Controller\System\IApiExceptionResponseController;
use Runway\Controller\IExceptionController;
use Runway\Request\IRequestRead;
use Runway\Request\Response;
use Throwable;

/**
 * A JSON error for an exception thrown outside the endpoint (e.g. in the routing) of an API request.
 */
class ExceptionControllerDecorator implements IExceptionController {
    use ApiPathTrait;

    public function __construct(
        protected IExceptionController            $inner,
        protected IRequestRead                    $request,
        protected IApiExceptionResponseController $apiErrorResponseController,
        protected string                          $pathPrefix = '/api'
    ) {}

    public function run(Throwable $exception): Response {
        return $this->isApiPath($this->request->getPath())
            ? $this->apiErrorResponseController->getResponse($exception)
            : $this->inner->run($exception);
    }
}
