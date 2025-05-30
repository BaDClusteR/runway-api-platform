<?php

namespace ApiPlatform;

use ApiPlatform\DTO\ApiRequestDTO;
use ApiPlatform\DTO\ApiResponseDTO;

interface IApi {
    public function execute(ApiRequestDTO $request): ApiResponseDTO;

    public function getVersion(): string;
}