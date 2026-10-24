<?php

namespace ApiPlatform\OpenAPI\Trait;

use ApiPlatform\Attribute\Assert\Length;
use ApiPlatform\Attribute\Assert\NotEmpty;
use ApiPlatform\Attribute\Assert\Range;
use ApiPlatform\Attribute\Docs\Argument;
use ApiPlatform\Attribute\Docs\Property;
use ApiPlatform\DTO\ApiEndpointArgumentFileDTO;
use ApiPlatform\OpenAPI\Enum\OpenApiEndpointParameterTypeEnum;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;

trait OpenApiParameterTrait {
    protected function getParameterType(
        ReflectionParameter|ReflectionProperty $reflection
    ): OpenApiEndpointParameterTypeEnum {
        // The names of the built-in types are lowercase, the class names are compared as is.
        return match ($this->getTypeName($reflection)) {
            "int"    => OpenApiEndpointParameterTypeEnum::TYPE_INTEGER,
            "float"  => OpenApiEndpointParameterTypeEnum::TYPE_NUMBER,
            // The uploaded file is a string of the multipart form.
            "string", ApiEndpointArgumentFileDTO::class => OpenApiEndpointParameterTypeEnum::TYPE_STRING,
            "bool"   => OpenApiEndpointParameterTypeEnum::TYPE_BOOLEAN,
            "array"  => OpenApiEndpointParameterTypeEnum::TYPE_ARRAY,
            default  => OpenApiEndpointParameterTypeEnum::TYPE_OBJECT
        };
    }

    /**
     * @return string Empty for no type or a union type (e.g. "int|string").
     */
    protected function getTypeName(ReflectionParameter|ReflectionProperty $reflection): string {
        $type = $reflection->getType();

        return ($type instanceof ReflectionNamedType) ? $type->getName() : '';
    }

    protected function getParameterFormat(
        Argument|Property|null $infoAttribute
    ): string {
        return (string)$infoAttribute?->format;
    }

    /**
     * @return array<mixed>
     */
    protected function getParameterEnum(
        Argument|Property|null $infoAttribute
    ): array {
        return (array)$infoAttribute?->enum;
    }

    protected function getParameterDefaultValue(
        ReflectionParameter|ReflectionProperty $reflection,
    ): mixed {
        try {
            return $reflection->getDefaultValue();
        } catch (ReflectionException) {
            return null;
        }
    }

    protected function getParameterExample(
        Argument|Property|null $infoAttribute
    ): mixed {
        return $infoAttribute?->example;
    }

    /**
     * @return array{0: int|float|null, 1: int|float|null} The minimum and the maximum of Range.
     */
    protected function getParameterRange(
        ReflectionParameter|ReflectionProperty $reflection,
    ): array {
        $range = $this->getFirstAttribute($reflection, Range::class);

        return [$range?->min, $range?->max];
    }

    /**
     * @return array{0: int|null, 1: int|null} The minimum and the maximum of Length.
     */
    protected function getParameterLength(
        ReflectionParameter|ReflectionProperty $reflection,
    ): array {
        $length = $this->getFirstAttribute($reflection, Length::class);

        return [$length?->minLength, $length?->maxLength];
    }

    protected function isAllowEmptyValue(
        ReflectionParameter|ReflectionProperty $reflection
    ): bool {
        return $this->getFirstAttribute($reflection, NotEmpty::class) === null;
    }

    /**
     * @template T of object
     *
     * @param ReflectionClass<object>|ReflectionParameter|ReflectionProperty $reflection
     * @param class-string<T>                                                $attributeFqn
     *
     * @return T|null
     */
    protected function getFirstAttribute(
        ReflectionClass|ReflectionParameter|ReflectionProperty $reflection,
        string                                                 $attributeFqn
    ): ?object {
        return ($attrs = $reflection->getAttributes($attributeFqn))
            ? $attrs[0]->newInstance()
            : null;
    }
}