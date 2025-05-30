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

        /** @var Parameter|null $argumentAttribute */
        $argumentAttribute = $this->getFirstAttribute($argumentReflection, Parameter::class);

        /** @var Argument|null $infoAttribute */
        $infoAttribute = $this->getFirstAttribute($argumentReflection, Argument::class);

        [$min, $max] = $this->getParameterRange($argumentReflection);
        [$minLength, $maxLength] = $this->getParameterLength($argumentReflection);

        return new OpenApiEndpointRequestParameterDTO(
            name: $argumentAttribute->name ?? $argumentName,
            type: $this->getParameterType($argumentReflection),
            source: $argumentAttribute->source,
            isRequired: !$argumentReflection->isOptional(),
            isNullable: $argumentReflection->allowsNull(),
            description: $infoAttribute->description,
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