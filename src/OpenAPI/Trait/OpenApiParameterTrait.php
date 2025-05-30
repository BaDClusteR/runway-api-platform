<?php

namespace ApiPlatform\OpenAPI\Trait;

use ApiPlatform\Attribute\Assert\Length;
use ApiPlatform\Attribute\Assert\NotEmpty;
use ApiPlatform\Attribute\Assert\Range;
use ApiPlatform\Attribute\Docs\Argument;
use ApiPlatform\Attribute\Docs\Property;
use ApiPlatform\OpenAPI\Enum\OpenApiEndpointParameterTypeEnum;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

trait OpenApiParameterTrait {
    protected function getParameterType(
        ReflectionParameter|ReflectionProperty $reflection
    ): OpenApiEndpointParameterTypeEnum {
        return match (strtolower($reflection->getType()?->getName())) {
            "int"    => OpenApiEndpointParameterTypeEnum::TYPE_INTEGER,
            "float"  => OpenApiEndpointParameterTypeEnum::TYPE_NUMBER,
            "string" => OpenApiEndpointParameterTypeEnum::TYPE_STRING,
            "bool"   => OpenApiEndpointParameterTypeEnum::TYPE_BOOLEAN,
            "array"  => OpenApiEndpointParameterTypeEnum::TYPE_ARRAY,
            default  => OpenApiEndpointParameterTypeEnum::TYPE_OBJECT
        };
    }

    protected function getParameterFormat(
        Argument|Property|null $infoAttribute
    ): string {
        return (string)$infoAttribute?->format;
    }

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
     * @return array{0: int|null, 1: int|null}
     */
    protected function getParameterRange(
        ReflectionParameter|ReflectionProperty $reflection,
    ): array {
        $result = [null, null];

        foreach ($reflection->getAttributes(Range::class) as $attribute) {
            /** @var Range $instance */
            $instance = $attribute->newInstance();

            if (
                $instance->min !== null
                && (
                    $result[0] === null
                    || $instance->min < $result[0]
                )
            ) {
                $result[0] = $instance->min;
            }

            if (
                $instance->max !== null
                && (
                    $result[1] === null
                    || $instance->max > $result[1]
                )
            ) {
                $result[1] = $instance->max;
            }
        }

        return $result;
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    protected function getParameterLength(
        ReflectionParameter|ReflectionProperty $reflection,
    ): array {
        $result = [null, null];

        foreach ($reflection->getAttributes(Length::class) as $attribute) {
            /** @var Length $instance */
            $instance = $attribute->newInstance();

            if (
                $instance->minLength !== null
                && (
                    $result[0] === null
                    || $instance->minLength < $result[0]
                )
            ) {
                $result[0] = $instance->minLength;
            }

            if (
                $instance->maxLength !== null
                && (
                    $result[1] === null
                    || $instance->maxLength > $result[1]
                )
            ) {
                $result[1] = $instance->maxLength;
            }
        }

        return $result;
    }

    protected function isAllowEmptyValue(
        ReflectionParameter|ReflectionProperty $reflection
    ): bool {
        return $this->getFirstAttribute($reflection, NotEmpty::class) === null;
    }

    protected function getFirstAttribute(
        ReflectionClass|ReflectionParameter|ReflectionProperty $reflection,
        string                                                 $attributeFqn
    ): mixed {
        return ($attrs = $reflection->getAttributes($attributeFqn))
            ? $attrs[0]->newInstance()
            : null;
    }
}