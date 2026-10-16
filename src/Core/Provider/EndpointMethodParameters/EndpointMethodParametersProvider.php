<?php

declare(strict_types=1);

namespace ApiPlatform\Core\Provider\EndpointMethodParameters;

use ApiPlatform\Attribute\Assert\IAssertion;
use ApiPlatform\Core\Provider\EndpointMethodParameterValue\IEndpointMethodParameterValueProvider;
use ApiPlatform\DTO\ApiEndpointDTO;
use ApiPlatform\DTO\ApiEndpointMethodParameterDTO;
use ApiPlatform\DTO\ApiRequestDTO;
use ApiPlatform\Exception\InternalErrorException;
use ApiPlatform\Exception\RequiredParameterNotProvidedException;
use ReflectionAttribute;
use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;

class EndpointMethodParametersProvider implements IEndpointMethodParametersProvider {
    public function __construct(
        protected IEndpointMethodParameterValueProvider $valueProvider
    ) {}

    /**
     * @return ApiEndpointMethodParameterDTO[]
     *
     * @throws InternalErrorException
     * @throws RequiredParameterNotProvidedException
     */
    public function getEndpointMethodParameters(ApiEndpointDTO $endpoint, ?ApiRequestDTO $request = null): array {
        try {
            $method = new ReflectionMethod($endpoint->class, $endpoint->method);
        } catch (ReflectionException $e) {
            throw new InternalErrorException($e->getMessage(), $e);
        }

        return array_map(
            fn(ReflectionParameter $parameter): ApiEndpointMethodParameterDTO => $this->buildParameterDTO(
                $endpoint,
                $parameter,
                $request
            ),
            $method->getParameters()
        );
    }

    /**
     * @throws InternalErrorException
     * @throws RequiredParameterNotProvidedException
     */
    protected function buildParameterDTO(
        ApiEndpointDTO      $endpoint,
        ReflectionParameter $parameter,
        ?ApiRequestDTO      $request
    ): ApiEndpointMethodParameterDTO {
        // The sources are defined (and checked) when the endpoints are collected, see IEndpointsProvider.
        $methodParameter = $endpoint->parameters[$parameter->getName()] ?? throw new InternalErrorException(
            "Argument '{$parameter->getName()}' of $endpoint->class::$endpoint->method() is not described."
        );

        return new ApiEndpointMethodParameterDTO(
            name: $methodParameter->name,
            argumentName: $methodParameter->argumentName,
            type: (string)$parameter->getType()?->getName(),
            isNullable: $parameter->allowsNull(),
            value: $request
                ? $this->valueProvider->getEndpointMethodParameterValue($methodParameter, $request)
                : null,
            assertions: $this->getParameterAssertions($parameter)
        );
    }

    /**
     * @return IAssertion[]
     */
    protected function getParameterAssertions(ReflectionParameter $parameter): array {
        return array_map(
            static fn(ReflectionAttribute $attribute): IAssertion => $attribute->newInstance(),
            $parameter->getAttributes(IAssertion::class, ReflectionAttribute::IS_INSTANCEOF)
        );
    }
}
