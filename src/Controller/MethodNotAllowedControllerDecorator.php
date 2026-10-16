<?php

declare(strict_types=1);

namespace ApiPlatform\Controller;

use ApiPlatform\Controller\System\IApiExceptionResponseController;
use ApiPlatform\Controller\System\IApiResponseController;
use ApiPlatform\DTO\ApiResponseDTO;
use ApiPlatform\Exception\MethodNotAllowedException;
use Runway\Controller\IMethodNotAllowedController;
use Runway\Request\IRequestRead;
use Runway\Request\Response;

/**
 * A JSON error for a wrong method of an API endpoint. An OPTIONS request (e.g. a CORS preflight) gets an empty
 * successful response with the allowed methods (and the CORS headers, see IApiResponseController).
 */
class MethodNotAllowedControllerDecorator implements IMethodNotAllowedController {
    use ApiPathTrait;

    public function __construct(
        protected IMethodNotAllowedController     $inner,
        protected IRequestRead                    $request,
        protected IApiResponseController          $responseController,
        protected IApiExceptionResponseController $errorResponseController,
        protected string                          $pathPrefix = '/api'
    ) {}

    public function run(array $allowedMethods): Response {
        if (!$this->isApiPath($this->request->getPath())) {
            return $this->inner->run($allowedMethods);
        }

        $allowedMethods = array_values(array_unique([...$allowedMethods, 'OPTIONS']));
        $allowHeader = ['Allow' => implode(', ', $allowedMethods)];

        if (strtoupper($this->request->getMethod()) === 'OPTIONS') {
            /** @var Response */
            return $this->responseController->getResponse(new ApiResponseDTO(data: [], code: 204, headers: $allowHeader));
        }

        return $this->errorResponseController
            ->getResponse(
                new MethodNotAllowedException($this->request->getPath(), $this->request->getMethod(), $allowedMethods)
            )
            ->addHeaders($allowHeader);
    }
}
