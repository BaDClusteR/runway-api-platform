<?php

declare(strict_types=1);

namespace ApiPlatform\Controller\System;

use ApiPlatform\DTO\ApiResponseDTO;
use Runway\Env\Provider\IEnvVariablesProvider;
use Runway\Logger\ILogger;
use Runway\Request\IRequest;
use Runway\Request\IResponse;
use JsonException;

class ApiResponseController implements IApiResponseController {
    public function __construct(
        protected IEnvVariablesProvider $envVariablesProvider,
        protected IResponse             $response,
        protected ILogger               $logger,
        protected IRequest              $request
    ) {}

    public function getResponse(ApiResponseDTO $response): IResponse {
        $responseCode = $this->getResponseCode($response);

        // Without the data: it can contain tokens and personal data.
        $this->logger->debug("API response", ['code' => $responseCode]);

        return $this->initResponse()
                    ->setCode($responseCode)
                    ->addHeaders(
                        $this->getResponseHeaders($response)
                    )
                    ->setBody(
                        $this->getResponseBody($response)
                    );
    }

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

    protected function getResponseHeaders(ApiResponseDTO $response): array {
        return $response->headers + $this->getSystemHeaders();
    }

    protected function getSystemHeaders(): array {
        return [
            'X-Request-Id' => $this->request->getRequestId()
        ];
    }

    protected function getResponseBody(ApiResponseDTO $response): string {
        $responseData = $this->getResponseData($response);
        if (!$responseData) {
            return "";
        }

        try {
            return json_encode(
                $responseData,
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
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

        return "";
    }

    protected function initResponse(): IResponse {
        $this->addCORSHeaders();
        $this->setContentTypeHeader();

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
        $allowedOrigins = $this->getAllowedOrigins();
        $isAnyOriginAllowed = in_array('*', $allowedOrigins, true);

        if ($origin === '' || (!$isAnyOriginAllowed && !in_array($origin, $allowedOrigins, true))) {
            return;
        }

        $this->response->addHeaders([
            'Access-Control-Allow-Origin'  => $isAnyOriginAllowed ? '*' : $origin,
            'Access-Control-Allow-Methods' => (string)$this->envVariablesProvider->getEnvVariable("API_ALLOW_METHODS"),
            'Access-Control-Allow-Headers' => (string)$this->envVariablesProvider->getEnvVariable("API_ALLOW_HEADERS"),
            // The response depends on the origin: a cache must not give it to another one.
            'Vary'                         => 'Origin',
        ]);
    }

    /**
     * @return string[] The origins of API_ALLOW_ORIGIN (comma-separated), e.g. ["https://app.example.com"] or ["*"].
     */
    protected function getAllowedOrigins(): array {
        return array_values(array_filter(
            array_map(
                static fn(string $origin): string => rtrim(trim($origin), '/'),
                explode(',', (string)$this->envVariablesProvider->getEnvVariable("API_ALLOW_ORIGIN"))
            ),
            static fn(string $origin): bool => $origin !== ''
        ));
    }
}