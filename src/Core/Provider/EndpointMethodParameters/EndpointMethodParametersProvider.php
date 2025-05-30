<?php

declare(strict_types=1);

namespace ApiPlatform\Core\Provider\EndpointMethodParameters;

use ApiPlatform\Attribute\Assert\IAssertion;
use ApiPlatform\Attribute\Parameter;
use ApiPlatform\Core\Provider\EndpointMethodParameterValue\IEndpointMethodParameterValueProvider;
use ApiPlatform\DTO\ApiEndpointDTO;
use ApiPlatform\DTO\ApiEndpointMethodParameterDTO;
use ApiPlatform\DTO\ApiMethodParameterDTO;
use ApiPlatform\DTO\ApiRequestDTO;
use ApiPlatform\Exception\InternalErrorException;
use ApiPlatform\Exception\RequiredParameterNotProvidedException;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;

class EndpointMethodParametersProvider implements IEndpointMethodParametersProvider {
    protected ?ApiRequestDTO $request = null;

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
        $this->request = $request;

        try {
            return $this->collectMethodParameters(
                $this->getEndpointMethodReflection($endpoint)
            );
        } catch (ReflectionException) {
            return [];
        }
    }

    /**
     * @throws ReflectionException
     */
    protected function getEndpointMethodReflection(ApiEndpointDTO $endpoint): ReflectionMethod {
        return new ReflectionClass($endpoint->class)->getMethod($endpoint->method);
    }

    /**
     * @return ApiEndpointMethodParameterDTO[]
     *
     * @throws InternalErrorException
     * @throws RequiredParameterNotProvidedException
     */
    protected function collectMethodParameters(ReflectionMethod $method): array {
        return array_map(
            fn(ReflectionParameter $parameter): ApiEndpointMethodParameterDTO => $this->buildParameterDTO($parameter),
            $method->getParameters()
        );
    }

    /**
     * @throws InternalErrorException
     * @throws RequiredParameterNotProvidedException
     */
    protected function buildParameterDTO(ReflectionParameter $parameter): ApiEndpointMethodParameterDTO {
        $parameterAttr = $parameter->getAttributes(Parameter::class)[0] ?? null;

        if (!$parameterAttr && !$parameter->isOptional()) {
            throw new InternalErrorException(
                "Parameter '{$parameter->getName()}' does not have a source nor default value."
            );
        }

        $methodParameterDTO = $this->buildApiMethodParameterDTO(
            $parameter,
            $parameterAttr->newInstance()
        );

        $value = $this->request
            ? $this->valueProvider->getEndpointMethodParameterValue(
                $methodParameterDTO,
                $this->request
            )
            : null;

        return new ApiEndpointMethodParameterDTO(
            name: $methodParameterDTO->name,
            argumentName: $methodParameterDTO->argumentName,
            type: (string)$parameter->getType()?->getName(),
            isNullable: $parameter->allowsNull(),
            value: $value,
            assertions: $this->getParameterAssertions($parameter)
        );
    }

    protected function buildApiMethodParameterDTO(
        ReflectionParameter $parameter,
        Parameter           $parameterAttr
    ): ApiMethodParameterDTO {
        $parameterName = $parameter->getName();

        try {
            $defaultValue = $parameter->getDefaultValue();
        } catch (ReflectionException) {
            $defaultValue = null;
        }

        return new ApiMethodParameterDTO(
            source: $parameterAttr->source,
            name: $parameterAttr->name ?? $parameterName,
            argumentName: $parameterName,
            hasDefaultValue: $parameter->isOptional(),
            defaultValue: $defaultValue,
        );
    }

    /**
     * @return IAssertion[]
     */
    protected function getParameterAssertions(ReflectionParameter $parameter): array {
        $assertions = [];

        foreach ($parameter->getAttributes() as $assertion) {
            if (in_array(IAssertion::class, class_implements($assertion->getName()), true)) {
                $assertions[] = $assertion->newInstance();
            }
        }

        return $assertions;
    }
}