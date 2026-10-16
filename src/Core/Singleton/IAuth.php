<?php

declare(strict_types=1);

namespace ApiPlatform\Core\Singleton;

use ApiPlatform\Core\Auth\DTO\SessionDTO;
use ApiPlatform\Exception\BadRequestException;
use ApiPlatform\Exception\TooManyRequestsException;

/**
 * The login of the only user: the credentials are set by a console command, a login starts a session.
 */
interface IAuth {
    /**
     * False if every endpoint is public (API_AUTH_ENABLED=false), e.g. the access is limited by the web server.
     */
    public function isEnabled(): bool;

    public function hasCredentials(): bool;

    /**
     * @return SessionDTO|null Null if the credentials are wrong.
     *
     * @throws TooManyRequestsException If there are too many failed logins.
     */
    public function logIn(string $login, string $password, string $ip, string $userAgent): ?SessionDTO;

    /**
     * Whether the token is of an active session.
     */
    public function isAuthenticated(string $token): bool;

    public function logOut(string $token): void;

    /**
     * All the sessions are ended.
     *
     * @throws BadRequestException If the login is empty or the password is too short.
     */
    public function setCredentials(string $login, string $password): void;
}
