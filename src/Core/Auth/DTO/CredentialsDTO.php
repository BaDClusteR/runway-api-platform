<?php

declare(strict_types=1);

namespace ApiPlatform\Core\Auth\DTO;

readonly class CredentialsDTO {
    /**
     * @param string $passwordHash See password_hash().
     */
    public function __construct(
        public string $login,
        public string $passwordHash
    ) {}
}
