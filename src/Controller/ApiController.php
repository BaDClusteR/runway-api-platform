<?php

declare(strict_types=1);

namespace ApiPlatform\Controller;

use ApiPlatform\Controller\System\IApiExceptionResponseController;
use ApiPlatform\Controller\System\IApiResponseController;
use ApiPlatform\Core\Provider\Endpoints\IEndpointsProvider;
use ApiPlatform\Core\Storage\ITokenStorage;
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
        protected ILogger                         $logger,
        protected ITokenStorage                   $tokenStorage
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

            $this->checkOrigin();

            return $this->responseController->getResponse(
                $this->api->execute($endpoint, $this->getApiRequestDTO($pathParameters))
            );
        } catch (ApiException $e) {
            return $this->errorResponseController->getResponse($e);
        }
    }

    /**
     * The browser sends the session cookie with any request to the site: a change made by a page of another site (CSRF)
     * is rejected. The cookie is SameSite=Strict anyway, this is the second line of defense.
     *
     * @throws ApiException
     */
    protected function checkOrigin(): void {
        $origin = (string)$this->request->getHeader('Origin');

        if (
            $origin === ''
            || in_array($this->request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)
            || !$this->tokenStorage->isCookieToken()
        ) {
            return;
        }

        $host = parse_url($origin, PHP_URL_HOST);
        $port = parse_url($origin, PHP_URL_PORT);
        $originAuthority = strtolower($host . (($port !== null) ? ":$port" : ''));

        if ($originAuthority !== strtolower($this->request->getHost())) {
            throw new ApiException(['The request is made by another site.'], 403);
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
