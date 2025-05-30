<?php

namespace ApiPlatform\Core\Singleton;

use ApiPlatform\Model\Token;

interface IAuth {
    public function isCredentialsCorrect(string $login, string $password): bool;

    public function isTokenValid(string $token): bool;

    public function generateToken(): Token;

    public function isAuthenticated(string $token): bool;

    public function updateTokenLastActiveDate(string $token): void;
}