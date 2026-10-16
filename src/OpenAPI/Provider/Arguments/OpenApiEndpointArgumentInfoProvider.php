<?php

declare(strict_types=1);

namespace ApiPlatform\OpenAPI\Provider\Arguments;

use ApiPlatform\Attribute\Docs\Argument;
use ApiPlatform\Attribute\Parameter;
use ApiPlatform\Core\Provider\EndpointMethodParameters\IEndpointMethodParametersProvider;
use ApiPlatform\DTO\ApiEndpointDTO;
use ApiPlatform\Exception\InternalErrorException;
use ApiPlatform\OpenAPI\DTO\OpenApiEndpointRequestParameterDTO;
use ApiPlatform\OpenAPI\Trait\OpenApiParameterTrait;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;

class OpenApiEndpointArgumentInfoProvider implements IOpenApiEndpointArgumentInfoProvider {
    use OpenApiParameterTrait;

    protected ?array $parameterDTOs = null;

    public function __construct(
        protected IEndpointMethodParametersProvider $parametersProvider
    ) {}

    /**
     * @throws ReflectionException
     * @throws InternalErrorException
     */
    public function getEndpointArgumentInfo(
        ApiEndpointDTO $endpoint,
        string         $argumentName
    ): OpenApiEndpointRequestParameterDTO {
        $argumentReflection = $this->getArgumentReflection($endpoint->class, $endpoint->method, $argumentName);

        // The sources are defined when the endpoints are collected: e.g. a path placeholder has no attribute.
        $parameter = $endpoint->parameters[$argumentName] ?? throw new InternalErrorException(
            "Argument \$$argumentName of $endpoint->class::$endpoint->method() is not described."
        );

        /** @var Argument|null $infoAttribute */
        $infoAttribute = $this->getFirstAttribute($argumentReflection, Argument::class);

        [$min, $max] = $this->getParameterRange($argumentReflection);
        [$minLength, $maxLength] = $this->getParameterLength($argumentReflection);

        return new OpenApiEndpointRequestParameterDTO(
            name: $parameter->name,
            type: $this->getParameterType($argumentReflection),
            source: $parameter->source,
            // The path parameters are always required in OpenAPI.
            isRequired: $parameter->source === Parameter::SOURCE_PATH || !$argumentReflection->isOptional(),
            isNullable: $argumentReflection->allowsNull(),
            description: (string)$infoAttribute?->description,
            format: $this->getParameterFormat($infoAttribute),
            default: $this->getParameterDefaultValue($argumentReflection),
            example: $this->getParameterExample($infoAttribute),
            allowEmptyValue: $this->isAllowEmptyValue($argumentReflection),
            enum: $this->getParameterEnum($infoAttribute),
            minimum: $min,
            maximum: $max,
            minLength: $minLength,
            maxLength: $maxLength,
        );
    }

    /**
     * @throws ReflectionException
     * @throws InternalErrorException
     */
    protected function getArgumentReflection(
        string $classFqn,
        string $methodName,
        string $argumentName
    ): ReflectionParameter {
        $reflectionClass = new ReflectionClass($classFqn);
        $reflectionMethod = $reflectionClass->getMethod($methodName);

        foreach ($reflectionMethod->getParameters() as $parameter) {
            if ($parameter->getName() === $argumentName) {
                return $parameter;
            }
        }

        throw new InternalErrorException("Cannot get a reflection of argument \${$argumentName} in {$classFqn}::{$reflectionMethod}");
    }
}