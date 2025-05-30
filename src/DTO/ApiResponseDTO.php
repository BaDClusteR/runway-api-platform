<?php

declare(strict_types=1);

namespace ApiPlatform\DTO;

readonly class ApiResponseDTO {
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public array|object $data,
        public int          $code = 200,
        public array        $headers = []
    ) {}
}