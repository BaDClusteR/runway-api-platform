<?php

declare(strict_types=1);

namespace ApiPlatform\Core\Provider\EndpointMethodParameterValue;

use ApiPlatform\DTO\ApiMethodParameterDTO;
use ApiPlatform\DTO\ApiRequestDTO;
use ApiPlatform\Exception\InternalErrorException;
use ApiPlatform\Exception\RequiredParameterNotProvidedException;
use Runway\Request\IRequest;

class EndpointMethodParameterValueProvider implements IEndpointMethodParameterValueProvider {
    public function __construct(
        protected IRequest $request
    ) {}

    /**
     * @throws RequiredParameterNotProvidedException
     * @throws InternalErrorException
     */
    public function getEndpointMethodParameterValue(ApiMethodParameterDTO $parameter, ApiRequestDTO $request): mixed {
        return match (strtolower($parameter->source)) {
            "query" => $this->getQueryParameterValue($parameter),
            "body"  => $this->getBodyParameterValue($parameter, $request),
            "path"  => $this->getPathParameterValue($parameter, $request),
            default => throw new InternalErrorException(
                "Argument '{$parameter->argumentName}' has invalid source: '{$parameter->source}'. Allowed sources are GET, POST and PATH."
            ),
        };
    }

    /**
     * @throws RequiredParameterNotProvidedException
     */
    protected function getQueryParameterValue(ApiMethodParameterDTO $parameter): mixed {
        if (!$this->request->hasGetParameter($parameter->name)) {
            if ($parameter->hasDefaultValue) {
                return $parameter->defaultValue;
            }

            throw new RequiredParameterNotProvidedException($parameter->name);
        }

        return $this->request->getGetParameter($parameter->name)->asRaw();
    }

    /**
     * @throws RequiredParameterNotProvidedException
     */
    protected function getBodyParameterValue(ApiMethodParameterDTO $parameter, ApiRequestDTO $request): mixed {
        if (!array_key_exists($parameter->name, $request->body)) {
            if ($parameter->hasDefaultValue) {
                return $parameter->defaultValue;
            }

            throw new RequiredParameterNotProvidedException($parameter->name);
        }

        return $request->body[$parameter->name];
    }

    /**
     * @throws InternalErrorException
     */
    protected function getPathParameterValue(ApiMethodParameterDTO $parameter, ApiRequestDTO $request): string {
        $value = match ($parameter->name) {
            "section"    => $request->section,
            "action"     => $request->action,
            "identifier" => $request->identifier,
            default      => throw new InternalErrorException(
                "Invalid name for path argument '{$parameter->argumentName}': {$parameter->name}. Allowed values are 'section', 'action' and 'identifier'"
            )
        };

        if ($value === null && $parameter->hasDefaultValue) {
            $value = $parameter->defaultValue;
        }

        return $value;
    }
}