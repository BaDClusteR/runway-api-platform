<?php

declare(strict_types=1);

namespace ApiPlatform\Controller\System;

use ApiPlatform\Core\Provider\AllowedOrigins\IAllowedOriginsProvider;
use ApiPlatform\Core\Storage\ISessionCookie;
use ApiPlatform\DTO\ApiResponseDTO;
use Runway\Env\Provider\IEnvVariablesProvider;
use Runway\Logger\ILogger;
use Runway\Request\IRequest;
use Runway\Request\Response;
use JsonException;

class ApiResponseController implements IApiResponseController {
    public function __construct(
        protected IEnvVariablesProvider   $envVariablesProvider,
        protected Response                $response,
        protected ILogger                 $logger,
        protected IRequest                $request,
        protected ISessionCookie          $sessionCookie,
        protected IAllowedOriginsProvider $allowedOriginsProvider
    ) {}

    public function getResponse(ApiResponseDTO $response): Response {
        $responseCode = $this->getResponseCode($response);
        $body = $this->getResponseBody($response);

        // The data cannot be encoded: an empty successful response would look like no data.
        if ($body === null) {
            $responseCode = 500;
            $body = '{"errors": [{"code": 500, "message": "Internal server error"}]}';
        }

        // Without the data: it can contain tokens and personal data.
        $this->logger->debug("API response", ['code' => $responseCode]);

        return $this->initResponse()
                    ->setCode($responseCode)
                    ->addHeaders(
                        $this->getResponseHeaders($response)
                    )
                    ->setBody($body);
    }

    /**
     * @return array<mixed>
     */
    protected function getResponseData(ApiResponseDTO $response): array {
        return is_object($response->data)
            ? get_object_vars($response->data)
            : $response->data;
    }

    protected function getResponseCode(ApiResponseDTO $response): int {
        return ($this->isSuccessFulResponse($response) && empty($this->getResponseData($response)))
            ? 204
            : $response->code;
    }

    protected function isSuccessFulResponse(ApiResponseDTO $response): bool {
        return $response->code >= 200 && $response->code <= 299;
    }

    /**
     * @return array<string, string>
     */
    protected function getResponseHeaders(ApiResponseDTO $response): array {
        return $response->headers + $this->getSystemHeaders();
    }

    /**
     * @return array<string, string>
     */
    protected function getSystemHeaders(): array {
        return [
            'X-Request-Id'           => $this->request->getRequestId(),
            // The browser must not take the JSON for HTML or a script, e.g. an error with the requested path.
            'X-Content-Type-Options' => 'nosniff',
            // The data of the user and the tokens must not stay in the caches (e.g. of a proxy).
            'Cache-Control'          => 'no-store',
        ];
    }

    /**
     * @return string|null Null if the data cannot be encoded.
     */
    protected function getResponseBody(ApiResponseDTO $response): ?string {
        $responseData = $this->getResponseData($response);
        if (!$responseData) {
            return "";
        }

        try {
            // A broken UTF-8 sequence (e.g. in an old imported text) is replaced rather than fails the whole response.
            return json_encode(
                $responseData,
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                | JSON_INVALID_UTF8_SUBSTITUTE
            );
        } catch (JsonException $e) {
            $this->logger->error(
                __METHOD__ . ": Cannot convert JSON error response to string. Reason: {$e->getMessage()}",
                [
                    'message' => $e->getMessage(),
                    'code'    => $e->getCode(),
                ]
            );
        }

        return null;
    }

    protected function initResponse(): Response {
        $this->addCORSHeaders();
        $this->setContentTypeHeader();

        // E.g. a login sets the session cookie, a logout deletes it.
        $cookie = $this->sessionCookie->takeChange();

        if ($cookie !== null) {
            $this->response->addCookie($cookie);
        }

        return $this->response;
    }

    protected function setContentTypeHeader(): void {
        $this->response->addHeader('Content-Type', 'application/json');
    }

    /**
     * The CORS headers are sent only for the allowed origins (API_ALLOW_ORIGIN): a request of the API site itself has
     * no "Origin" header or the same origin, and needs no CORS.
     */
    protected function addCORSHeaders(): void {
        $origin = (string)$this->request->getHeader('Origin');

        if (!$this->allowedOriginsProvider->isAllowed($origin)) {
            return;
        }

        $this->response->addHeaders([
            'Access-Control-Allow-Origin'  => $this->allowedOriginsProvider->isAnyAllowed() ? '*' : $origin,
            'Access-Control-Allow-Methods' => $this->getEnvString('API_ALLOW_METHODS'),
            'Access-Control-Allow-Headers' => $this->getEnvString('API_ALLOW_HEADERS'),
            // The response depends on the origin: a cache must not give it to another one.
            'Vary'                         => 'Origin',
        ]);
    }

    protected function getEnvString(string $name): string {
        $value = $this->envVariablesProvider->getEnvVariable($name);

        return is_scalar($value) ? (string)$value : '';
    }
}
