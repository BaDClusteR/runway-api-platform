<?php

declare(strict_types=1);

namespace ApiPlatform\DTO;

use Runway\Request\Parameters\DTO\FileDTO;

readonly class ApiRequestDTO {
    /**
     * @param FileDTO[] $files
     */
    public function __construct(
        public ?string $section,
        public ?string $subSection,
        public ?string $identifier,
        public string  $method,
        public array   $body,
        public array   $files,
    ) {}
}