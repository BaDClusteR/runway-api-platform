<?php

declare(strict_types=1);

namespace ApiPlatform\OpenAPI\Provider;

use ApiPlatform\Attribute\Docs\Endpoint;
use ApiPlatform\Attribute\Docs\Group;
use ApiPlatform\DTO\ApiEndpointDTO;
use ApiPlatform\Exception\InternalErrorException;
use ApiPlatform\OpenAPI\DTO\OpenApiEndpointInfoDTO;
use ApiPlatform\OpenAPI\Provider\Arguments\IOpenApiEndpointArgumentInfoProvider;
use ApiPlatform\OpenAPI\Provider\Response\IOpenApiEndpointResponseSchemaProvider;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;

class OpenApiEndpointInfoProvider implements IOpenApiEndpointInfoProvider {
    public function __construct(
        protected IOpenApiEndpointArgumentInfoProvider   $argumentInfoProvider,
        protected IOpenApiEndpointResponseSchemaProvider $responseSchemaProvider
    ) {}

    /**
     * @throws ReflectionException
     * @throws InternalErrorException
     */
    public function getEndpointInfo(ApiEndpointDTO $endpoint): OpenApiEndpointInfoDTO {
        $classReflection = new ReflectionClass($endpoint->class);
        $methodReflection = $classReflection->getMethod($endpoint->method);
        $endpointAttribute = $this->getEndpointAttribute($methodReflection);

        return new OpenApiEndpointInfoDTO(
            group: $this->getGroupByClassReflection($classReflection),
            title: (string)$endpointAttribute?->title,
            description: (string)($endpointAttribute?->description ?: $endpointAttribute?->title),
            operationId: (string)($endpointAttribute?->operationId ?: $endpointAttribute?->title),
            arguments: $this->getArguments($methodReflection, $endpoint),
            isPublic: $endpoint->isPublic,
            responseSchema: $this->responseSchemaProvider->getEndpointResponseSchema($endpoint),
            isDeprecated: false
        );
    }

    protected function getGroupByClassReflection(ReflectionClass $classReflection): string {
        $groupAttributes = $classReflection->getAttributes(Group::class);

        if ($groupAttributes) {
            /** @var Group $groupInstance */
            $groupInstance = $groupAttributes[0]->newInstance();

            return $groupInstance->name;
        }

        return "";
    }

    protected function getEndpointAttribute(ReflectionMethod $methodReflection): ?Endpoint {
        return ($methodAttributes = $methodReflection->getAttributes(Endpoint::class))
            ? $methodAttributes[0]->newInstance()
            : null;
    }

    /**
     * @throws InternalErrorException
     * @throws ReflectionException
     */
    protected function getArguments(ReflectionMethod $methodReflection, ApiEndpointDTO $endpoint): array {
        return array_map(
            fn(ReflectionParameter $parameter) => $this->argumentInfoProvider->getEndpointArgumentInfo(
                $endpoint,
                $parameter->getName()
            ),
            $methodReflection->getParameters()
        );
    }
}