<?php

declare(strict_types=1);

namespace ApiPlatform;

use ApiPlatform\Core\Singleton\IAuth;
use ApiPlatform\Core\Storage\ITokenStorage;
use ApiPlatform\DTO\ApiEndpointDTO;
use ApiPlatform\DTO\ApiEndpointMethodParameterDTO;
use ApiPlatform\DTO\ApiRequestDTO;
use ApiPlatform\DTO\ApiResponseDTO;
use ApiPlatform\Exception\ApiException;
use ApiPlatform\Exception\AuthorizationRequiredException;
use ApiPlatform\Exception\BadRequestException;
use ApiPlatform\Exception\InternalErrorException;
use ApiPlatform\Core\Provider\EndpointMethodParameters\IEndpointMethodParametersProvider;
use ApiPlatform\Core\Validator\IEndpointMethodParameterValidator;
use Runway\Singleton\Container;

class Api implements IApi {
    public function __construct(
        protected IEndpointMethodParametersProvider $parametersProvider,
        protected IEndpointMethodParameterValidator $parameterValidator,
        protected IAuth                             $auth,
        protected ITokenStorage                     $tokenStorage,
        protected string                            $apiPlatformVersion
    ) {}

    public function execute(ApiEndpointDTO $endpoint, ApiRequestDTO $request): ApiResponseDTO {
        // The session activity is updated by the check.
        if (!$this->isEndpointAccessible($endpoint)) {
            throw new AuthorizationRequiredException();
        }

        $parameters = $this->parametersProvider->getEndpointMethodParameters($endpoint, $request);

        $this->validateEndpointMethodParameters($parameters);

        return $this->callEndpointMethod($endpoint, $parameters);
    }

    protected function isEndpointAccessible(ApiEndpointDTO $endpoint): bool {
        return $endpoint->isPublic
            || !$this->auth->isEnabled()
            || $this->auth->isAuthenticated(
                $this->tokenStorage->getToken()
            );
    }

    /**
     * @param ApiEndpointMethodParameterDTO[] $parameters
     *
     * @throws InternalErrorException
     */
    protected function callEndpointMethod(ApiEndpointDTO $endpoint, array $parameters): ApiResponseDTO {
        $endpointInstance = Container::getInstance()->getService($endpoint->class);

        $result = $endpointInstance->{$endpoint->method}(
            ...$this->buildEndpointMethodArguments($parameters)
        );

        if (!is_object($result)) {
            $resultType = get_debug_type($result);

            throw new InternalErrorException(
                "API endpoint method '{$endpoint->class}::{$endpoint->method}' is expected to return a DTO object, returned $resultType."
            );
        }

        return new ApiResponseDTO(
            data: $result
        );
    }

    /**
     * @param ApiEndpointMethodParameterDTO[] $parameters
     *
     * @return array<string, mixed>
     */
    protected function buildEndpointMethodArguments(array $parameters): array {
        $result = [];

        foreach ($parameters as $parameter) {
            if ($parameter->isNullable && $parameter->value === null) {
                $result[$parameter->argumentName] = null;
            } else {
                $result[$parameter->argumentName] = $this->castArgumentValueToType($parameter->value, $parameter->type);
            }
        }

        return $result;
    }

    /**
     * The value is validated (see IEndpointMethodParameterValidator): e.g. an "int" one is numeric, an "array" one is
     * an array.
     */
    protected function castArgumentValueToType(mixed $value, string $type): mixed {
        return match (true) {
            $type === "int" && is_numeric($value)    => (int)$value,
            $type === "float" && is_numeric($value)  => (float)$value,
            // (bool)"false" would be true.
            $type === "bool"                         => is_string($value)
                ? filter_var($value, FILTER_VALIDATE_BOOLEAN)
                : (bool)$value,
            $type === "string" && is_scalar($value)  => (string)$value,
            default                                  => $value
        };
    }

    /**
     * @param ApiEndpointMethodParameterDTO[] $endpointParameters
     *
     * @throws BadRequestException
     */
    protected function validateEndpointMethodParameters(array $endpointParameters): void {
        $errors = [];
        $isValidated = true;

        foreach ($endpointParameters as $parameter) {
            $validationResult = $this->parameterValidator->validate($parameter);

            if (!$validationResult->isValidated) {
                $isValidated = false;
            }

            $errors = [...$errors, ...$validationResult->errors];
        }

        if (!$isValidated) {
            throw new BadRequestException($errors);
        }
    }

    public function getVersion(): string {
        return $this->apiPlatformVersion;
    }
}