<?php

declare(strict_types=1);

namespace ApiPlatform\DTO;

use Runway\Request\Parameters\DTO\FileDTO;

readonly class ApiRequestDTO {
    /**
     * @param array<string, string> $pathParameters Placeholder name => value.
     * @param array<mixed>          $body           The decoded JSON body.
     * @param FileDTO[]             $files          Field name => file.
     */
    public function __construct(
        public string $method,
        public array  $pathParameters,
        public array  $body,
        public array  $files,
    ) {}
}
