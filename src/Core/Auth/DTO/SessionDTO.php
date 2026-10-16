<?php

declare(strict_types=1);

namespace ApiPlatform\Core\Auth\DTO;

/**
 * A new session: the token is known only now, the storage has its hash.
 */
readonly class SessionDTO {
    /**
     * @param int $validUntil Unix timestamp.
     */
    public function __construct(
        public string $token,
        public int    $validUntil
    ) {}
}
