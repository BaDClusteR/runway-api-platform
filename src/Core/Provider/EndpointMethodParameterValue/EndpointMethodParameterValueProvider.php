<?php

declare(strict_types=1);

namespace ApiPlatform\Core\Provider\EndpointMethodParameterValue;

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
        return match (strtolower($parameter->source)) {
            "query" => $this->getQueryParameterValue($parameter),
            "body"  => $this->getBodyParameterValue($parameter, $request),
            "path"  => $this->getPathParameterValue($parameter, $request),
            "file"  => $this->getFile($parameter, $request),
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
            "subSection" => $request->subSection,
            "identifier" => $request->identifier,
            default      => throw new InternalErrorException(
                "Invalid name for path argument '{$parameter->argumentName}': {$parameter->name}. Allowed values are 'section', 'subSection' and 'identifier'"
            )
        };

        if ($value === null && $parameter->hasDefaultValue) {
            $value = $parameter->defaultValue;
        }

        return $value;
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