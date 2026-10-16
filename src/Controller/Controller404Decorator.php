<?php

declare(strict_types=1);

namespace ApiPlatform\Controller;

use ApiPlatform\Controller\System\IApiExceptionResponseController;
use ApiPlatform\Exception\EndpointNotFoundException;
use Runway\Controller\IController404;
use Runway\Request\IRequestRead;
use Runway\Request\Response;

/**
 * A JSON error for a non-existent API endpoint.
 */
class Controller404Decorator implements IController404 {
    use ApiPathTrait;

    public function __construct(
        protected IController404                  $inner,
        protected IRequestRead                    $request,
        protected IApiExceptionResponseController $errorResponseController,
        protected string                          $pathPrefix = '/api'
    ) {}

    public function run(): Response {
        if (!$this->isApiPath($this->request->getPath())) {
            return $this->inner->run();
        }

        return $this->errorResponseController->getResponse(
            new EndpointNotFoundException($this->request->getPath(), $this->request->getMethod())
        );
    }
}
