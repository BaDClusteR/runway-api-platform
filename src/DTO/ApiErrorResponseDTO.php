<?php

declare(strict_types=1);

namespace ApiPlatform\DTO;

use Throwable;

readonly class ApiErrorResponseDTO {
    public function __construct(
        public ?Throwable $exception = null,
    ) {}
}