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
use ApiPlatform\Exception\EndpointNotFoundException;
use ApiPlatform\Exception\InternalErrorException;
use ApiPlatform\Core\Provider\EndpointMethodParameters\IEndpointMethodParametersProvider;
use ApiPlatform\Core\Provider\Endpoints\IEndpointsProvider;
use ApiPlatform\Core\Validator\IEndpointMethodParameterValidator;
use Runway\Request\IRequest;
use Runway\Request\IResponse;
use Runway\Singleton\Container;

class Api implements IApi {
    public function __construct(
        protected IEndpointsProvider                $endpointsProvider,
        protected IEndpointMethodParametersProvider $parametersProvider,
        protected IEndpointMethodParameterValidator $parameterValidator,
        protected IRequest                          $request,
        protected IAuth                             $auth,
        protected ITokenStorage                     $tokenStorage,
        protected IResponse                         $response,
        protected string                            $apiPlatformVersion
    ) {}

    /**
     * @throws ApiException
     */
    public function execute(ApiRequestDTO $request): ApiResponseDTO {
        if ($endpoint = $this->getRequestEndpoint($request)) {
            if (!$this->isEndpointAccessible($endpoint)) {
                throw new AuthorizationRequiredException();
            }

            $this->auth->updateTokenLastActiveDate(
                $this->tokenStorage->getToken()
            );

            $parameters = $this->parametersProvider->getEndpointMethodParameters($endpoint, $request);

            $this->validateEndpointMethodParameters($parameters);

            return $this->callEndpointMethod($endpoint, $parameters);
        }

        throw new EndpointNotFoundException(
            $this->request->getPath(),
            $this->request->getMethod()
        );
    }

    protected function isEndpointAccessible(ApiEndpointDTO $endpoint): bool {
        return $endpoint->isPublic
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
     * @return array<string, int|float|string|array|null>
     */
    protected function buildEndpointMethodArguments(array $parameters): array {
        $result = [];

        foreach ($parameters as $parameter) {
            $result[$parameter->argumentName] = $this->castArgumentValueToType($parameter->value, $parameter->type);
        }

        return $result;
    }

    protected function castArgumentValueToType(mixed $value, string $type): mixed {
        return match ($type) {
            "int"    => (int)$value,
            "float"  => (float)$value,
            "bool"   => (bool)$value,
            "string" => (string)$value,
            "array"  => (array)$value,
            default  => $value
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

    protected function getRequestEndpoint(ApiRequestDTO $request): ?ApiEndpointDTO {
        $endpointPath = $this->buildEndpointPath($request);

        return array_find(
            $this->endpointsProvider->getApiEndpoints(),
            static fn(ApiEndpointDTO $endpoint) => (
                $endpoint->path === $endpointPath
                && $endpoint->requestMethod === $request->method
            )
        );

    }

    protected function buildEndpointPath(ApiRequestDTO $request): string {
        $result = (string)$request->section;

        if ($request->action) {
            $result .= ($result ? "/" : "") . $request->action;
        }

        return $result;
    }

    public function getVersion(): string {
        return $this->apiPlatformVersion;
    }
}