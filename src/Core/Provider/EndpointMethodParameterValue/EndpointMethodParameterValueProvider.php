<?php

declare(strict_types=1);

namespace ApiPlatform\Core\Provider\EndpointMethodParameterValue;

use ApiPlatform\Attribute\Parameter;
use ApiPlatform\DTO\ApiEndpointArgumentFileDTO;
use ApiPlatform\DTO\ApiMethodParameterDTO;
use ApiPlatform\DTO\ApiRequestDTO;
use ApiPlatform\Exception\BadRequestException;
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
     * @throws BadRequestException
     */
    public function getEndpointMethodParameterValue(ApiMethodParameterDTO $parameter, ApiRequestDTO $request): mixed {
        return match ($parameter->source) {
            Parameter::SOURCE_QUERY => $this->getQueryParameterValue($parameter),
            Parameter::SOURCE_BODY  => $this->getBodyParameterValue($parameter, $request),
            Parameter::SOURCE_PATH  => $this->getPathParameterValue($parameter, $request),
            Parameter::SOURCE_FILE  => $this->getFile($parameter, $request),
            // Not taken from the request.
            ''                      => $parameter->defaultValue,
            default                 => throw new InternalErrorException(
                "Argument '{$parameter->argumentName}' has invalid source: '{$parameter->source}'."
            ),
        };
    }

    /**
     * @throws RequiredParameterNotProvidedException
     */
    protected function getQueryParameterValue(ApiMethodParameterDTO $parameter): mixed {
        $hasParameter = $this->request->getMethod() === "GET"
            ? $this->request->hasGetParameter($parameter->name)
            : $this->request->hasPostParameter($parameter->name);

        if (!$hasParameter) {
            if ($parameter->hasDefaultValue) {
                return $parameter->defaultValue;
            }

            throw new RequiredParameterNotProvidedException($parameter->name);
        }

        return $this->request->getMethod() === "GET"
            ? $this->request->getGetParameter($parameter->name)->asRaw()
            : $this->request->getPostParameter($parameter->name)->asRaw();
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
    /**
     * The router has matched the path, so every placeholder has a value.
     *
     * @throws InternalErrorException
     */
    protected function getPathParameterValue(ApiMethodParameterDTO $parameter, ApiRequestDTO $request): string {
        return $request->pathParameters[$parameter->name] ?? throw new InternalErrorException(
            "Path placeholder {{$parameter->name}} of argument '{$parameter->argumentName}' has no value."
        );
    }

    /**
     * @throws BadRequestException
     * @throws InternalErrorException
     */
    protected function getFile(ApiMethodParameterDTO $parameter, ApiRequestDTO $request): ApiEndpointArgumentFileDTO {
        if (isset($request->files[$parameter->name])) {
            $file = $request->files[$parameter->name];

            $uploadError = (int)$file->getError();

            if ($uploadError !== UPLOAD_ERR_OK) {
                $this->throwFileRelatedException($parameter->name, $uploadError);
            }

            return new ApiEndpointArgumentFileDTO(
                name: $file->getName(),
                mimeType: $file->getType(),
                tmpName: $file->getTmpName(),
                size: $file->getSize(),
            );
        }

        throw new BadRequestException(
            ["Required file '$parameter->name' is not found in the request"]
        );
    }

    /**
     * @throws BadRequestException
     * @throws InternalErrorException
     */
    protected function throwFileRelatedException(string $parameterName, int $errorCode): never {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE: throw new BadRequestException(
                ["File '$parameterName': file size exceeds the maximum upload size"]
            );
            case UPLOAD_ERR_FORM_SIZE: throw new BadRequestException(
                ["File '$parameterName': file size exceeds the maximum upload size that was specified in the HTML form"]
            );
            case UPLOAD_ERR_PARTIAL: throw new BadRequestException(
                ["File '$parameterName': file was only partially uploaded"]
            );
            case UPLOAD_ERR_NO_FILE: throw new InternalErrorException(
                "There is no file '$parameterName' uploaded."
            );
            case UPLOAD_ERR_NO_TMP_DIR: throw new InternalErrorException(
                "Cannot upload file '$parameterName': missing a temporary folder"
            );
            case UPLOAD_ERR_CANT_WRITE: throw new InternalErrorException(
                "Cannot upload file '$parameterName': unable to write the file to the disk"
            );
            case UPLOAD_ERR_EXTENSION: throw new InternalErrorException(
                "Cannot upload file '$parameterName': upload stopped by PHP extension"
            );
        }

        throw new InternalErrorException("Cannot upload file '$parameterName': unknown error");
    }
}