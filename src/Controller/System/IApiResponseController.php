<?php

namespace ApiPlatform\Controller\System;

use ApiPlatform\DTO\ApiResponseDTO;
use Runway\Request\IResponse;

interface IApiResponseController {
    public function getResponse(ApiResponseDTO $response): IResponse;
}