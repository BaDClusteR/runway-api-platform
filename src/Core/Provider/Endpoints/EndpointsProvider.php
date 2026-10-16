<?php

declare(strict_types=1);

namespace ApiPlatform\Core\Provider\Endpoints;

use ApiPlatform\Attribute\Endpoint;
use ApiPlatform\Attribute\Parameter;
use ApiPlatform\Attribute\Prefix;
use ApiPlatform\DTO\ApiEndpointDTO;
use ApiPlatform\DTO\ApiMethodParameterDTO;
use ApiPlatform\Exception\EndpointDefinitionException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Runway\Router\Compiler\IRouteCompiler;
use Runway\Service\DTO\RouteDTO;
use Runway\Service\Exception\Route\RouteException;
use Runway\Singleton\Container;

/**
 * Collects the endpoints from the Endpoint attributes (and its shortcuts: Get, Post, etc.) of the services with the
 * API_ENDPOINT_TAG_NAME tag, and checks them: every path placeholder needs an argument and vice versa, every argument
 * needs a source (a placeholder, a Parameter attribute) or a default value.
 */
class EndpointsProvider implements IEndpointsProvider {
    public const string API_ENDPOINT_TAG_NAME = 'api_endpoint_collection';

    /**
     * The values of the placeholders of these argument types are checked by the router: e.g. "/chats/abc" is not found
     * rather than called with chatId = 0. An inline requirement ("{id<-?\d+>}") wins.
     */
    protected const array TYPE_REQUIREMENTS = [
        'int'   => '\d+',
        'float' => '\d+(?:\.\d+)?',
        'bool'  => '0|1|true|false',
    ];

    protected const array SOURCES = [
        Parameter::SOURCE_PATH,
        Parameter::SOURCE_QUERY,
        Parameter::SOURCE_BODY,
        Parameter::SOURCE_FILE,
    ];

    /**
     * @var array<string, ApiEndpointDTO>|null Route name => endpoint.
     */
    protected ?array $endpoints = null;

    /**
     * @param array<object|class-string>|null $endpointServices By default, the services with the API_ENDPOINT_TAG_NAME
     *                                                          tag.
     */
    public function __construct(
        protected IRouteCompiler $routeCompiler,
        protected ?array         $endpointServices = null
    ) {}

    public function getApiEndpoints(): array {
        return array_values($this->collectApiEndpoints());
    }

    public function getEndpointByRouteName(string $routeName): ?ApiEndpointDTO {
        return $this->collectApiEndpoints()[$routeName] ?? null;
    }

    /**
     * @return array<string, ApiEndpointDTO>
     *
     * @throws EndpointDefinitionException
     */
    protected function collectApiEndpoints(): array {
        if ($this->endpoints === null) {
            $endpoints = [];

            foreach ($this->getEndpointServices() as $service) {
                foreach ($this->getClassEndpoints($service) as $endpoint) {
                    $endpoints[$endpoint->routeName] = $endpoint;
                }
            }

            $this->endpoints = $endpoints;
        }

        return $this->endpoints;
    }

    /**
     * @return array<object|class-string>
     */
    protected function getEndpointServices(): array {
        return $this->endpointServices ??= Container::getInstance()->getServicesByTag(static::API_ENDPOINT_TAG_NAME);
    }

    /**
     * @return ApiEndpointDTO[]
     *
     * @throws EndpointDefinitionException
     */
    protected function getClassEndpoints(object|string $classInstanceOrFqn): array {
        try {
            $class = new ReflectionClass($classInstanceOrFqn);
        } catch (ReflectionException $e) {
            throw new EndpointDefinitionException($e->getMessage(), $e);
        }

        $prefix = $this->getClassPrefix($class);
        $endpoints = [];

        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(Endpoint::class, ReflectionAttribute::IS_INSTANCEOF);

            foreach ($attributes as $index => $attribute) {
                $endpoints[] = $this->buildEndpoint(
                    $class,
                    $method,
                    $attribute->newInstance(),
                    $prefix,
                    // A method can have several endpoints (e.g. an old path and a new one).
                    $this->getRouteName($class, $method) . ($index ? ".$index" : '')
                );
            }
        }

        return $endpoints;
    }

    protected function getClassPrefix(ReflectionClass $class): string {
        $attribute = $class->getAttributes(Prefix::class)[0] ?? null;

        return $attribute ? $attribute->newInstance()->path : '';
    }

    protected function getRouteName(ReflectionClass $class, ReflectionMethod $method): string {
        return "api.{$class->getName()}::{$method->getName()}";
    }

    /**
     * @throws EndpointDefinitionException
     */
    protected function buildEndpoint(
        ReflectionClass  $class,
        ReflectionMethod $method,
        Endpoint         $attribute,
        string           $prefix,
        string           $routeName
    ): ApiEndpointDTO {
        $path = $this->joinPaths($prefix, $attribute->path);
        $endpointName = "{$class->getName()}::{$method->getName()}()";
        $placeholders = $this->getPlaceholders($path, $routeName, $endpointName);

        $parameters = [];
        $requirements = [];

        foreach ($method->getParameters() as $reflectionParameter) {
            $parameter = $this->buildParameter($reflectionParameter, $placeholders, $endpointName);
            $parameters[$parameter->argumentName] = $parameter;

            if ($parameter->source !== Parameter::SOURCE_PATH) {
                continue;
            }

            if (!in_array($parameter->name, $placeholders, true)) {
                throw new EndpointDefinitionException(
                    "$endpointName: argument \${$parameter->argumentName} is taken from the path placeholder"
                    . " {{$parameter->name}}, but path \"$path\" has no such placeholder."
                );
            }

            $typeRequirement = $this->getTypeRequirement($reflectionParameter);

            if ($typeRequirement !== null) {
                $requirements[$parameter->name] = $typeRequirement;
            }
        }

        $this->checkPlaceholderArguments($placeholders, $parameters, $path, $endpointName);

        return new ApiEndpointDTO(
            path: $path,
            requestMethod: strtoupper($attribute->method),
            class: $class->getName(),
            method: $method->getName(),
            isPublic: $attribute->public,
            routeName: $routeName,
            requirements: $requirements,
            parameters: $parameters
        );
    }

    protected function joinPaths(string ...$paths): string {
        return implode(
            '/',
            array_filter(
                array_map(static fn(string $path): string => trim($path, '/'), $paths),
                static fn(string $path): bool => $path !== ''
            )
        );
    }

    /**
     * @return string[] The placeholder names.
     *
     * @throws EndpointDefinitionException If the path template is invalid.
     */
    protected function getPlaceholders(string $path, string $routeName, string $endpointName): array {
        try {
            return $this->routeCompiler->compile(
                new RouteDTO($routeName, "/$path", '', '', 0, '', $endpointName)
            )->variables;
        } catch (RouteException $e) {
            throw new EndpointDefinitionException("$endpointName: {$e->getMessage()}", $e);
        }
    }

    /**
     * @param string[] $placeholders
     *
     * @throws EndpointDefinitionException
     */
    protected function buildParameter(
        ReflectionParameter $parameter,
        array               $placeholders,
        string              $endpointName
    ): ApiMethodParameterDTO {
        $argumentName = $parameter->getName();
        $attribute = ($parameter->getAttributes(Parameter::class, ReflectionAttribute::IS_INSTANCEOF)[0] ?? null)
            ?->newInstance();

        // Without the attribute, an argument with the name of a placeholder is taken from the path.
        $source = $attribute->source
            ?? (in_array($argumentName, $placeholders, true) ? Parameter::SOURCE_PATH : '');

        if ($source !== '' && !in_array($source, static::SOURCES, true)) {
            throw new EndpointDefinitionException(
                "$endpointName: argument \$$argumentName has invalid source \"$source\", allowed sources are "
                . implode(', ', static::SOURCES) . '.'
            );
        }

        if ($source === '' && !$parameter->isDefaultValueAvailable()) {
            throw new EndpointDefinitionException(
                "$endpointName: argument \$$argumentName has no source: it's not a path placeholder, and it has"
                . ' neither a Parameter attribute nor a default value.'
            );
        }

        return new ApiMethodParameterDTO(
            source: $source,
            name: $attribute->name ?? $argumentName,
            argumentName: $argumentName,
            hasDefaultValue: $parameter->isDefaultValueAvailable(),
            defaultValue: $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null
        );
    }

    /**
     * @param string[]                             $placeholders
     * @param array<string, ApiMethodParameterDTO> $parameters
     *
     * @throws EndpointDefinitionException
     */
    protected function checkPlaceholderArguments(
        array  $placeholders,
        array  $parameters,
        string $path,
        string $endpointName
    ): void {
        foreach ($placeholders as $placeholder) {
            $hasArgument = array_any(
                $parameters,
                static fn(ApiMethodParameterDTO $parameter): bool => $parameter->source === Parameter::SOURCE_PATH
                    && $parameter->name === $placeholder
            );

            if (!$hasArgument) {
                throw new EndpointDefinitionException(
                    "$endpointName: path \"$path\" has placeholder {{$placeholder}}, but the method has no argument"
                    . ' for it.'
                );
            }
        }
    }

    protected function getTypeRequirement(ReflectionParameter $parameter): ?string {
        $type = $parameter->getType();

        return ($type instanceof ReflectionNamedType)
            ? (static::TYPE_REQUIREMENTS[$type->getName()] ?? null)
            : null;
    }
}
