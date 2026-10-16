<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Fixture;

use ApiPlatform\Core\Auth\DTO\CredentialsDTO;
use ApiPlatform\Core\Auth\DTO\StoredSessionDTO;
use ApiPlatform\Core\Auth\IAuthStorage;

/**
 * The authorization data for the tests, without a database.
 */
class InMemoryAuthStorage implements IAuthStorage {
    public ?CredentialsDTO $credentials = null;

    /** @var array<string, StoredSessionDTO> Token hash => session. */
    public array $sessions = [];

    /** @var array<array{ip: string, time: int}> */
    public array $attempts = [];

    public function getCredentials(): ?CredentialsDTO {
        return $this->credentials;
    }

    public function setCredentials(CredentialsDTO $credentials): void {
        $this->credentials = $credentials;
    }

    public function addSession(string $tokenHash, int $validUntil, string $ip, string $userAgent): void {
        $this->sessions[$tokenHash] = new StoredSessionDTO(count($this->sessions) + 1, $validUntil, time(), true);
    }

    public function findSession(string $tokenHash): ?StoredSessionDTO {
        return $this->sessions[$tokenHash] ?? null;
    }

    public function touchSession(int $sessionId, int $time): void {
    }

    public function deactivateSession(string $tokenHash): void {
        if (isset($this->sessions[$tokenHash])) {
            $session = $this->sessions[$tokenHash];
            $this->sessions[$tokenHash] = new StoredSessionDTO($session->id, $session->validUntil, $session->lastActive, false);
        }
    }

    public function deactivateAllSessions(): void {
        foreach (array_keys($this->sessions) as $tokenHash) {
            $this->deactivateSession($tokenHash);
        }
    }

    public function addFailedAttempt(string $ip, int $time): void {
        $this->attempts[] = ['ip' => $ip, 'time' => $time];
    }

    public function countFailedAttempts(?string $ip, int $since): int {
        return count(array_filter(
            $this->attempts,
            static fn(array $attempt): bool => $attempt['time'] >= $since && ($ip === null || $attempt['ip'] === $ip)
        ));
    }

    public function clearFailedAttempts(string $ip): void {
        $this->attempts = array_values(array_filter($this->attempts, static fn(array $attempt): bool => $attempt['ip'] !== $ip));
    }
}
