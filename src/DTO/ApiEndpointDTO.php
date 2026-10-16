<?php

declare(strict_types=1);

namespace ApiPlatform\DTO;

readonly class ApiEndpointDTO {
    /**
     * @param string                               $path         The path template relative to the API prefix, e.g.
     *                                                           "chats/{chatId}/chunks/{index}".
     * @param string                               $routeName    The name of the router route of the endpoint.
     * @param array<string, string>                $requirements Placeholder name => regexp of its value (see
     *                                                           Runway\Router\Compiler\RouteCompiler).
     * @param array<string, ApiMethodParameterDTO> $parameters   The method arguments by name, with their sources.
     */
    public function __construct(
        public string $path,
        public string $requestMethod,
        public string $class,
        public string $method,
        public bool   $isPublic,
        public string $routeName = '',
        public array  $requirements = [],
        public array  $parameters = []
    ) {}
}
