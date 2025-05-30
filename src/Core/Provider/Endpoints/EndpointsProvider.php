<?php

declare(strict_types=1);

namespace ApiPlatform\Core\Provider\Endpoints;

use ApiPlatform\Attribute\Endpoint;
use ApiPlatform\DTO\ApiEndpointDTO;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Runway\Singleton\Container;

class EndpointsProvider implements IEndpointsProvider {
    public const string API_ENDPOINT_TAG_NAME = 'api_endpoint_collection';

    /**
     * @var ApiEndpointDTO[]
     */
    protected static ?array $endpoints = null;

    /**
     * @return ApiEndpointDTO[]
     */
    public function getApiEndpoints(): array {
        $this->collectApiEndpoints();

        return static::$endpoints;
    }

    protected function collectApiEndpoints(): void {
        if (static::$endpoints !== null) {
            return;
        }

        static::$endpoints = [];

        foreach ($this->getEndpointServices() as $serviceInstance) {
            static::$endpoints = [...static::$endpoints, ...$this->getClassEndpoints($serviceInstance)];
        }
    }

    protected function getEndpointServices(): array {
        return Container::getInstance()->getServicesByTag(static::API_ENDPOINT_TAG_NAME);
    }

    /**
     * @return ApiEndpointDTO[]
     */
    protected function getClassEndpoints(string|object $classInstanceOrFqn): array {
        try {
            $class = new ReflectionClass($classInstanceOrFqn);

            /** @var ApiEndpointDTO[] $endpoints */
            $endpoints = [];

            foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $endpoints = [...$endpoints, ...$this->getMethodEndpoints($method)];
            }

            return $endpoints;
        } catch (ReflectionException) {
            return [];
        }
    }

    /**
     * @return ApiEndpointDTO[]
     */
    protected function getMethodEndpoints(ReflectionMethod $method): array {
        $methodName = $method->getName();
        $className = $method->getDeclaringClass()->getName();

        return array_map(
            fn(ReflectionAttribute $attribute): ApiEndpointDTO => $this->getEndpointByAttribute(
                $attribute->newInstance(),
                $className,
                $methodName
            ),
            $method->getAttributes(Endpoint::class)
        );
    }

    protected function getEndpointByAttribute(
        Endpoint $attrInstance,
        string   $className,
        string   $methodName
    ): ApiEndpointDTO {
        return new ApiEndpointDTO(
            path: ltrim($attrInstance->path, "/"),
            requestMethod: $attrInstance->method,
            class: $className,
            method: $methodName,
            isPublic: $attrInstance->public
        );
    }
}