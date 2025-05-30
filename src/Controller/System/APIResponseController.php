<?php

declare(strict_types=1);

namespace ApiPlatform\Controller\System;

use ApiPlatform\DTO\ApiResponseDTO;
use Runway\Env\Provider\IEnvVariablesProvider;
use Runway\Logger\ILogger;
use Runway\Request\IRequest;
use Runway\Request\IResponse;
use JsonException;

class APIResponseController implements IApiResponseController {
    public function __construct(
        protected IEnvVariablesProvider $envVariablesProvider,
        protected IResponse             $response,
        protected ILogger               $logger,
        protected IRequest              $request
    ) {}

    public function getResponse(ApiResponseDTO $response): IResponse {
        $responseCode = $this->getResponseCode($response);

        $this->logger->debug(
            "API response",
            [
                'code'    => $responseCode,
                'data'    => $this->getResponseData($response),
                'headers' => $response->headers
            ]
        );

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
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT
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

    protected function addCORSHeaders(): void {
        $this->response->addHeaders([
            'Access-Control-Allow-Origin'  => $this->envVariablesProvider->getEnvVariable("API_ALLOW_ORIGIN"),
            'Access-Control-Allow-Methods' => $this->envVariablesProvider->getEnvVariable("API_ALLOW_METHODS"),
            'Access-Control-Allow-Headers' => $this->envVariablesProvider->getEnvVariable("API_ALLOW_HEADERS"),
        ]);
    }
}