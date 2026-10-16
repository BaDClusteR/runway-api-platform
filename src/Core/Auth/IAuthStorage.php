<?php

declare(strict_types=1);

namespace ApiPlatform\Core\Auth;

use ApiPlatform\Core\Auth\DTO\CredentialsDTO;
use ApiPlatform\Core\Auth\DTO\StoredSessionDTO;

/**
 * The data of the authorization: the credentials of the only user, the sessions (by the token hashes) and the failed
 * login attempts.
 */
interface IAuthStorage {
    public function getCredentials(): ?CredentialsDTO;

    public function setCredentials(CredentialsDTO $credentials): void;

    /**
     * @param int $validUntil Unix timestamp.
     */
    public function addSession(string $tokenHash, int $validUntil, string $ip, string $userAgent): void;

    public function findSession(string $tokenHash): ?StoredSessionDTO;

    public function touchSession(int $sessionId, int $time): void;

    public function deactivateSession(string $tokenHash): void;

    public function deactivateAllSessions(): void;

    public function addFailedAttempt(string $ip, int $time): void;

    /**
     * @param string|null $ip Null for all the addresses.
     */
    public function countFailedAttempts(?string $ip, int $since): int;

    public function clearFailedAttempts(string $ip): void;
}
