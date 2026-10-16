<?php

declare(strict_types=1);

namespace ApiPlatform\Router;

use ApiPlatform\Controller\IApiController;
use ApiPlatform\Core\Provider\Endpoints\IEndpointsProvider;
use ApiPlatform\DTO\ApiEndpointDTO;
use Runway\Router\Provider\IRouteProvider;
use Runway\Service\DTO\RouteDTO;

/**
 * The API endpoints are the router routes: the router matches the path and the method, checks the placeholder
 * requirements, and then IApiController calls the endpoint.
 */
class ApiRouteProvider implements IRouteProvider {
    public function __construct(
        protected IEndpointsProvider $endpointsProvider,
        protected string             $pathPrefix = '/api'
    ) {}

    public function getRoutes(): array {
        return array_map($this->buildRoute(...), $this->endpointsProvider->getApiEndpoints());
    }

    protected function buildRoute(ApiEndpointDTO $endpoint): RouteDTO {
        return new RouteDTO(
            name: $endpoint->routeName,
            path: rtrim($this->pathPrefix, '/') . '/' . $endpoint->path,
            controller: IApiController::class,
            method: 'handle',
            priority: 0,
            description: "$endpoint->class::$endpoint->method()",
            filePath: $endpoint->class,
            methods: [$endpoint->requestMethod],
            requirements: $endpoint->requirements
        );
    }
}
