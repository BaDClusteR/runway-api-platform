<?php

declare(strict_types=1);

namespace ApiPlatform\Endpoint;

use ApiPlatform\Attribute as API;
use ApiPlatform\IApi;
use ApiPlatform\OpenAPI\IOpenApiGenerator;
use JsonException;
use Runway\Request\IResponse;
use Runway\Singleton\IKernel;

class Schema {
    public function __construct(
        protected IKernel           $kernel,
        protected IResponse         $response,
        protected IOpenApiGenerator $openApiGenerator,
        protected IApi              $api
    ) {}

    /**
     * @throws JsonException
     */
    #[API\Endpoint(path: "schema", method: "GET")]
    public function generateOpenApiSchema(): never {
        $this->kernel->processResponse(
            $this->getResponse()
        );

        exit(0);
    }

    /**
     * @throws JsonException
     */
    protected function getResponse(): IResponse {
        return $this->response
            ->setBody(
                json_encode(
                    $this->openApiGenerator->generateOpenApiSpec(),
                    JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT
                )
            );
    }

    protected function getFilename(): string {
        return "schema-{$this->api->getVersion()}.json";
    }
}