<?php

declare(strict_types=1);

namespace ApiPlatform\Controller;

use ApiPlatform\Controller\System\IApiExceptionResponseController;
use ApiPlatform\Controller\System\IApiResponseController;
use ApiPlatform\Core\Provider\AllowedOrigins\IAllowedOriginsProvider;
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
    /** The requests of these methods change nothing. */
    protected const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(
        protected IRequest                        $request,
        protected IApi                            $api,
        protected IEndpointsProvider              $endpointsProvider,
        protected IApiResponseController          $responseController,
        protected IApiExceptionResponseController $errorResponseController,
        protected ILogger                         $logger,
        protected ITokenStorage                   $tokenStorage,
        protected IAllowedOriginsProvider         $allowedOriginsProvider
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
                $this->api->execute(
                    $endpoint,
                    // The router passes them by name.
                    $this->getApiRequestDTO(array_filter($pathParameters, is_string(...), ARRAY_FILTER_USE_KEY))
                )
            );
        } catch (ApiException $e) {
            return $this->errorResponseController->getResponse($e);
        }
    }

    /**
     * A page of another site can send a request to the API from the browser of a user (CSRF): the browser adds the
     * session cookie, and the web server can let it in by the IP address or the cached basic auth credentials (e.g.
     * with API_AUTH_ENABLED=false). So a change made by another site is rejected, unless it's an allowed one
     * (API_ALLOW_ORIGIN) and the request has no session cookie: the cookie is for the pages of the API site only (it's
     * SameSite=Strict anyway, this is the second line of defense).
     *
     * A script sends no "Origin" header, and a page of another site cannot send the token header without CORS.
     *
     * @throws ApiException
     */
    protected function checkOrigin(): void {
        $origin = (string)$this->request->getHeader('Origin');
        $isCookieToken = $this->tokenStorage->isCookieToken();
        $isHeaderToken = !$isCookieToken && $this->tokenStorage->getToken() !== '';

        if (
            $origin === ''
            || in_array(strtoupper($this->request->getMethod()), static::SAFE_METHODS, true)
            || $isHeaderToken
            || $this->isSameSiteOrigin($origin)
            || (!$isCookieToken && $this->allowedOriginsProvider->isAllowed($origin))
        ) {
            return;
        }

        throw new ApiException(['The request is made by another site.'], 403);
    }

    /**
     * The host and the port of the origin are the ones of the request (the scheme can differ behind a proxy).
     */
    protected function isSameSiteOrigin(string $origin): bool {
        $host = (string)parse_url($origin, PHP_URL_HOST);
        $port = parse_url($origin, PHP_URL_PORT);
        $originAuthority = strtolower($host . (is_int($port) ? ":$port" : ''));

        return $host !== '' && $originAuthority === strtolower($this->request->getHost());
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
     * @return array<mixed>
     *
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
