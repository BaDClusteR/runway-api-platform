<?php

declare(strict_types=1);

namespace ApiPlatform\OpenAPI\Provider\Response;

use ApiPlatform\Attribute\Docs\Property;
use ApiPlatform\Attribute\Docs\Response;
use ApiPlatform\DTO\ApiEndpointDTO;
use ApiPlatform\Exception\InternalErrorException;
use ApiPlatform\OpenAPI\DTO\OpenApiEndpointResponseParameterDTO;
use ApiPlatform\OpenAPI\DTO\OpenApiEndpointResponseSchemaDTO;
use ApiPlatform\OpenAPI\Trait\OpenApiParameterTrait;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

class OpenApiEndpointResponseSchemaProvider implements IOpenApiEndpointResponseSchemaProvider {
    use OpenApiParameterTrait;

    /**
     * @throws InternalErrorException
     */
    public function getEndpointResponseSchema(ApiEndpointDTO $endpoint): OpenApiEndpointResponseSchemaDTO {
        try {
            $classReflection = new ReflectionClass($endpoint->class);
            $methodReflection = $classReflection->getMethod($endpoint->method);
        } catch (ReflectionException) {
            throw new InternalErrorException("Cannot get reflection of {$endpoint->class}::{$endpoint->method}");
        }

        $returnType = $methodReflection->getReturnType()?->getName();
        if (!class_exists($returnType)) {
            throw new InternalErrorException(
                "Class {$returnType} returned by {$endpoint->class}::{$endpoint->method} does not exist"
            );
        }

        $returnTypeReflection = new ReflectionClass($returnType);

        return new OpenApiEndpointResponseSchemaDTO(
            schema: $this->getResponseSchema($returnTypeReflection),
            refName: $this->getRefName($returnType),
            description: $this->getResponseSchemaDescription($returnTypeReflection)
        );
    }

    protected function getRefName(string $returnTypeFqn): string {
        return str_replace(
            ['ApiPlatform\\API\\Endpoint\\DTO\\', '\\'],
            ['', '.'],
            $returnTypeFqn
        );
    }

    protected function getResponseSchemaDescription(ReflectionClass $dtoReflection): string {
        return (string)$this->getResponseInfoAttribute($dtoReflection)?->description;
    }

    protected function getResponseInfoAttribute(ReflectionClass $dtoReflection): ?Response {
        return ($attrs = $dtoReflection->getAttributes(Response::class))
            ? $attrs[0]->newInstance()
            : null;
    }

    protected function getResponseSchema(ReflectionClass $dtoReflection): array {
        return array_map(
            fn(ReflectionProperty $prop): OpenApiEndpointResponseParameterDTO => $this->buildOpenApiResponseParameter(
                $prop,
            ),
            $this->getResponseDTOProperties($dtoReflection)
        );
    }

    protected function getResponseDTOProperties(ReflectionClass $responseDTOReflection): array {
        return $responseDTOReflection->getProperties(ReflectionProperty::IS_PUBLIC);
    }

    protected function buildOpenApiResponseParameter(ReflectionProperty $prop): OpenApiEndpointResponseParameterDTO {
        /** @var Property|null $infoAttribute */
        $infoAttribute = $this->getFirstAttribute($prop, Property::class);

        [$min, $max] = $this->getParameterRange($prop);
        [$minLength, $maxLength] = $this->getParameterLength($prop);

        return new OpenApiEndpointResponseParameterDTO(
            name: $prop->getName(),
            type: $this->getParameterType($prop),
            isNullable: $prop->getType()?->allowsNull() ?? true,
            description: $infoAttribute->description,
            format: $this->getParameterFormat($infoAttribute),
            default: $prop->getDefaultValue(),
            example: $this->getParameterExample($infoAttribute),
            enum: $this->getParameterEnum($infoAttribute),
            minimum: $min,
            maximum: $max,
            minLength: $minLength,
            maxLength: $maxLength
        );
    }
}