<?php

namespace ApiPlatform\Controller\System;

use ApiPlatform\DTO\ApiResponseDTO;
use Runway\Request\Response;

interface IApiResponseController {
    /**
     * The JSON response: the CORS headers, the session cookie change, 204 for no data.
     */
    public function getResponse(ApiResponseDTO $response): Response;
}
