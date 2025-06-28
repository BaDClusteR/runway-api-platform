<?php

declare(strict_types=1);

namespace ApiPlatform\Controller;

use ApiPlatform\Controller\System\IApiExceptionResponseController;
use ApiPlatform\Controller\System\IApiResponseController;
use ApiPlatform\DTO\ApiRequestDTO;
use ApiPlatform\Exception\ApiException;
use ApiPlatform\Exception\EndpointNotFoundException;
use ApiPlatform\Exception\UnprocessableContentException;
use ApiPlatform\IApi;
use JsonException;
use Runway\Logger\ILogger;
use Runway\Request\IRequest;
use Runway\Request\Response;
use Runway\Singleton\Container;

class ApiRouter implements IApiRouter {
    public function __construct(
        protected IRequest $request,
        protected ILogger  $logger
    ) {}

    public function route(
        string  $apiVersion = "v1",
        ?string $section = null,
        ?string $subSection = null,
        ?string $identifier = null
    ): Response {
        $this->logger->debug(
            "API request",
            [
                'apiVersion' => $apiVersion,
                'section'    => $section,
                'subSection' => $subSection,
                'identifier' => $identifier,
                'method'     => $this->request->getMethod(),
                'body'       => $this->request->getBody()
            ]
        );

        try {
            if ($api = $this->getAPIByVersion(strtolower($apiVersion))) {
                return $this->getResponseController()->getResponse(
                    $api->execute(
                        $this->getApiRequestDTO($section, $subSection, $identifier)
                    )
                );
            }

            throw new EndpointNotFoundException(
                $this->request->getPath(),
                $this->request->getMethod(),
            );

        } catch (ApiException $e) {
            return $this->getErrorResponseController()->getResponse($e);
        }
    }

    /**
     * @throws UnprocessableContentException
     */
    protected function getApiRequestDTO(?string $section, ?string $subSection, ?string $identifier): ApiRequestDTO {
        $body = [];

        try {
            $rawBody = $this->request->getBody();

            if ($rawBody) {
                $body = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);

                if (!is_array($body)) {
                    throw new UnprocessableContentException("Request body is not valid JSON object or array");
                }
            }
        } catch (JsonException) {
            throw new UnprocessableContentException("Request body is not valid JSON");
        }

        return new ApiRequestDTO(
            section: $section,
            subSection: $subSection,
            identifier: $identifier,
            method: $this->request->getMethod(),
            body: $body,
            files: $this->request->getFiles()
        );
    }

    protected function getResponseController(): IApiResponseController {
        return Container::getInstance()->getService(IApiResponseController::class);
    }

    protected function getErrorResponseController(): IApiExceptionResponseController {
        return Container::getInstance()->getService(IApiExceptionResponseController::class);
    }

    protected function getAPIByVersion(string $version): ?IApi {
        return match ($version) {
            "v1"    => Container::getInstance()->getService(IApi::class),
            default => null
        };
    }
}