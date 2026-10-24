<?php

declare(strict_types=1);

namespace ApiPlatform\OpenAPI\Provider\Response;

use ApiPlatform\Attribute\Docs\Property;
use ApiPlatform\Attribute\Docs\Response;
use ApiPlatform\DTO\ApiEndpointDTO;
use ApiPlatform\Exception\InternalErrorException;
use ApiPlatform\OpenAPI\DTO\OpenApiEndpointResponseParameterDTO;
use ApiPlatform\OpenAPI\DTO\OpenApiEndpointResponseSchemaDTO;
use ApiPlatform\OpenAPI\Enum\OpenApiEndpointParameterTypeEnum;
use ApiPlatform\OpenAPI\Trait\OpenApiParameterTrait;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use Runway\Logger\ILogger;

class OpenApiEndpointResponseSchemaProvider implements IOpenApiEndpointResponseSchemaProvider {
    use OpenApiParameterTrait;

    public function __construct(
        protected ILogger $logger
    ) {
    }

    /**
     * @throws InternalErrorException
     */
    public function getEndpointResponseSchema(ApiEndpointDTO $endpoint): OpenApiEndpointResponseSchemaDTO {
        try {
            $classReflection = new ReflectionClass($endpoint->class);
            $methodReflection = $classReflection->getMethod($endpoint->method);
        } catch (ReflectionException) {
            throw new InternalErrorException("Cannot get reflection of $endpoint->class::$endpoint->method");
        }

        $returnType = $methodReflection->getReturnType();
        $returnTypeName = ($returnType instanceof ReflectionNamedType) ? $returnType->getName() : (string)$returnType;

        if (!class_exists($returnTypeName)) {
            throw new InternalErrorException(
                "Class $returnTypeName returned by $endpoint->class::$endpoint->method does not exist"
            );
        }

        return $this->getResponseSchemaDTOByFqn($returnTypeName);
    }

    /**
     * The name of the schema in the components, e.g. "Auth.LoginDTO" for the DTOs of the platform endpoints.
     */
    protected function getRefName(string $returnTypeFqn): string {
        return str_replace(
            ['ApiPlatform\\Endpoint\\DTO\\', '\\'],
            ['', '.'],
            $returnTypeFqn
        );
    }

    /**
     * @param ReflectionClass<object> $dtoReflection
     */
    protected function getResponseSchemaDescription(ReflectionClass $dtoReflection): string {
        return (string)$this->getResponseInfoAttribute($dtoReflection)?->description;
    }

    /**
     * @param ReflectionClass<object> $dtoReflection
     */
    protected function getResponseInfoAttribute(ReflectionClass $dtoReflection): ?Response {
        return $this->getFirstAttribute($dtoReflection, Response::class);
    }

    /**
     * @param ReflectionClass<object> $dtoReflection
     *
     * @return OpenApiEndpointResponseParameterDTO[]
     */
    protected function getResponseSchema(ReflectionClass $dtoReflection): array {
        return array_map(
            fn(ReflectionProperty $prop): OpenApiEndpointResponseParameterDTO => $this->buildOpenApiResponseParameter(
                $prop,
            ),
            $this->getResponseDTOProperties($dtoReflection)
        );
    }

    /**
     * @param ReflectionClass<object> $responseDTOReflection
     *
     * @return ReflectionProperty[]
     */
    protected function getResponseDTOProperties(ReflectionClass $responseDTOReflection): array {
        return $responseDTOReflection->getProperties(ReflectionProperty::IS_PUBLIC);
    }

    protected function buildOpenApiResponseParameter(ReflectionProperty $prop): OpenApiEndpointResponseParameterDTO {
        $infoAttribute = $this->getFirstAttribute($prop, Property::class);

        [$min, $max] = $this->getParameterRange($prop);
        [$minLength, $maxLength] = $this->getParameterLength($prop);

        return new OpenApiEndpointResponseParameterDTO(
            name: $prop->getName(),
            type: $this->getParameterType($prop),
            isNullable: $prop->getType()?->allowsNull() ?? true,
            description: (string)$infoAttribute?->description,
            format: $this->getParameterFormat($infoAttribute),
            default: $prop->hasDefaultValue() ? $prop->getDefaultValue() : null,
            example: $this->getParameterExample($infoAttribute),
            enum: $this->getParameterEnum($infoAttribute),
            minimum: $min,
            maximum: $max,
            minLength: $minLength,
            maxLength: $maxLength,
            children: $this->getChildren($prop)
        );
    }

    protected function getChildren(ReflectionProperty $prop): OpenApiEndpointResponseSchemaDTO|string|null {
        $parameterType = $this->getParameterType($prop);

        if ($parameterType === OpenApiEndpointParameterTypeEnum::TYPE_OBJECT) {
            try {
                return $this->getResponseSchemaDTOByFqn($this->getTypeName($prop));
            } catch (InternalErrorException) {
            }
        } elseif ($parameterType === OpenApiEndpointParameterTypeEnum::TYPE_ARRAY) {
            $childrenType = (string)$this->getFirstAttribute($prop, Property::class)?->childrenType;

            if ($this->isPrimitiveType($childrenType)) {
                return $childrenType;
            }

            try {
                return $this->getResponseSchemaDTOByFqn($childrenType);
            } catch (InternalErrorException) {
            }
        }

        return null;
    }

    protected function isPrimitiveType(string $type): bool {
        // "object" and "mixed" are free-form values: e.g. tree nodes of different kinds.
        return in_array(
            strtolower($type),
            ['string', 'int', 'float', 'bool', 'object', 'mixed'],
            true
        );
    }

    /**
     * @throws InternalErrorException
     */
    protected function getResponseSchemaDTOByFqn(string $fqn): OpenApiEndpointResponseSchemaDTO {
        if (!class_exists($fqn)) {
            throw new InternalErrorException("Cannot get reflection of $fqn: the class does not exist");
        }

        $returnTypeReflection = new ReflectionClass($fqn);

        return new OpenApiEndpointResponseSchemaDTO(
            schema: $this->getResponseSchema($returnTypeReflection),
            refName: $this->getRefName($fqn),
            description: $this->getResponseSchemaDescription($returnTypeReflection)
        );
    }
}
