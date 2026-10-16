<?php

namespace ApiPlatform\Core\Singleton;

use ApiPlatform\Model\Token;

interface IAuth {
    /**
     * False if every endpoint is public (API_AUTH_ENABLED=false), e.g. the access is limited by the web server.
     */
    public function isEnabled(): bool;

    public function isCredentialsCorrect(string $login, string $password): bool;

    public function isTokenValid(string $token): bool;

    public function generateToken(): Token;

    public function isAuthenticated(string $token): bool;

    public function updateTokenLastActiveDate(string $token): void;
}
