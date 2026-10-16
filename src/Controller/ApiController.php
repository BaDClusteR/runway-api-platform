<?php

declare(strict_types=1);

namespace ApiPlatform\Controller;

use ApiPlatform\Controller\System\IApiExceptionResponseController;
use ApiPlatform\Controller\System\IApiResponseController;
use ApiPlatform\Core\Provider\Endpoints\IEndpointsProvider;
use ApiPlatform\DTO\ApiEndpointDTO;
use ApiPlatform\DTO\ApiRequestDTO;
use ApiPlatform\Exception\ApiException;
use ApiPlatform\Exception\EndpointNotFoundException;
use ApiPlatform\Exception\UnprocessableContentException;
use ApiPlatform\IApi;
use JsonException;
use Runway\Logger\ILogger;
use Runway\Request\IRequest;
use Runway\Request\Response;

class ApiController implements IApiController {
    public function __construct(
        protected IRequest                        $request,
        protected IApi                            $api,
        protected IEndpointsProvider              $endpointsProvider,
        protected IApiResponseController          $responseController,
        protected IApiExceptionResponseController $errorResponseController,
        protected ILogger                         $logger
    ) {}

    public function handle(string ...$pathParameters): Response {
        try {
            $endpoint = $this->getEndpoint();

            // Only the routing details: the body can contain passwords and personal data.
            $this->logger->debug(
                'API request',
                [
                    'method'   => $this->request->getMethod(),
                    'path'     => $this->request->getPath(),
                    'endpoint' => "$endpoint->class::$endpoint->method",
                ]
            );

            return $this->responseController->getResponse(
                $this->api->execute($endpoint, $this->getApiRequestDTO($pathParameters))
            );
        } catch (ApiException $e) {
            return $this->errorResponseController->getResponse($e);
        }
    }

    /**
     * @throws ApiException
     */
    protected function getEndpoint(): ApiEndpointDTO {
        $routeName = $this->request->getRouteMatch()?->route->name;
        $endpoint = ($routeName !== null) ? $this->endpointsProvider->getEndpointByRouteName($routeName) : null;

        return $endpoint ?? throw new EndpointNotFoundException($this->request->getPath(), $this->request->getMethod());
    }

    /**
     * @param array<string, string> $pathParameters
     *
     * @throws UnprocessableContentException
     */
    protected function getApiRequestDTO(array $pathParameters): ApiRequestDTO {
        return new ApiRequestDTO(
            method: $this->request->getMethod(),
            pathParameters: $pathParameters,
            body: $this->getBody(),
            files: $this->request->getFiles()
        );
    }

    /**
     * @throws UnprocessableContentException
     */
    protected function getBody(): array {
        $rawBody = $this->request->getBody();

        if ($rawBody === '') {
            return [];
        }

        try {
            $body = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new UnprocessableContentException("Request body is not valid JSON");
        }

        if (!is_array($body)) {
            throw new UnprocessableContentException("Request body is not valid JSON object or array");
        }

        return $body;
    }
}
