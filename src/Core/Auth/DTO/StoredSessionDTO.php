<?php

declare(strict_types=1);

namespace ApiPlatform\Core\Auth\DTO;

readonly class StoredSessionDTO {
    /**
     * @param int $validUntil Unix timestamp.
     * @param int $lastActive Unix timestamp.
     */
    public function __construct(
        public int  $id,
        public int  $validUntil,
        public int  $lastActive,
        public bool $isActive
    ) {}
}
