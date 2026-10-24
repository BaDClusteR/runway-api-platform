<?php

declare(strict_types=1);

namespace ApiPlatform\Core\Auth;

use ApiPlatform\Core\Auth\DTO\CredentialsDTO;
use ApiPlatform\Core\Auth\DTO\StoredSessionDTO;
use Runway\DataStorage\IDataStorageDriver;
use Runway\Singleton\Container;

/**
 * The tables (without the prefix):
 * - options (name, value): the credentials;
 * - tokens (token, generated, valid_until, last_active, ip, user_agent, server_info, expired, active): the sessions,
 *   the "token" is the hash of the token;
 * - login_attempts (ip, attempted): the failed logins.
 */
class AuthStorage implements IAuthStorage {
    protected const string LOGIN_OPTION = 'auth_login';

    protected const string PASSWORD_HASH_OPTION = 'auth_password_hash';

    /** The failed attempts older than this are deleted, in seconds. */
    protected const int ATTEMPTS_TTL = 86400;

    public function getCredentials(): ?CredentialsDTO {
        $login = $this->getOption(static::LOGIN_OPTION);
        $passwordHash = $this->getOption(static::PASSWORD_HASH_OPTION);

        return ($login !== null && $passwordHash !== null) ? new CredentialsDTO($login, $passwordHash) : null;
    }

    public function setCredentials(CredentialsDTO $credentials): void {
        $this->setOption(static::LOGIN_OPTION, $credentials->login);
        $this->setOption(static::PASSWORD_HASH_OPTION, $credentials->passwordHash);
    }

    public function addSession(string $tokenHash, int $validUntil, string $ip, string $userAgent): void {
        $now = time();

        $this->getDriver()->execute(
            'INSERT INTO `{tokens}` (token, generated, valid_until, last_active, ip, user_agent, server_info, expired, active)'
            . ' VALUES (:token, :generated, :validUntil, :lastActive, :ip, :userAgent, \'\', 0, 1)',
            [
                'token'      => $tokenHash,
                'generated'  => $now,
                'validUntil' => $validUntil,
                'lastActive' => $now,
                'ip'         => $ip,
                'userAgent'  => $userAgent,
            ]
        );
    }

    public function findSession(string $tokenHash): ?StoredSessionDTO {
        $row = $this->getDriver()->getFirstResult(
            'SELECT id, valid_until, last_active, active, expired FROM `{tokens}` WHERE token = :token',
            ['token' => $tokenHash]
        );

        return $row
            ? new StoredSessionDTO(
                id: $this->toInt($row['id'] ?? null),
                validUntil: $this->toInt($row['valid_until'] ?? null),
                lastActive: $this->toInt($row['last_active'] ?? null),
                isActive: $this->toInt($row['active'] ?? null) !== 0 && $this->toInt($row['expired'] ?? null) === 0
            )
            : null;
    }

    public function touchSession(int $sessionId, int $time): void {
        $this->getDriver()->execute(
            'UPDATE `{tokens}` SET last_active = :time WHERE id = :id',
            ['time' => $time, 'id' => $sessionId]
        );
    }

    public function deactivateSession(string $tokenHash): void {
        $this->getDriver()->execute('UPDATE `{tokens}` SET active = 0 WHERE token = :token', ['token' => $tokenHash]);
    }

    public function deactivateAllSessions(): void {
        $this->getDriver()->execute('UPDATE `{tokens}` SET active = 0 WHERE active = 1');
    }

    public function addFailedAttempt(string $ip, int $time): void {
        $driver = $this->getDriver();
        $driver->execute(
            'DELETE FROM `{login_attempts}` WHERE attempted < :before',
            ['before' => $time - static::ATTEMPTS_TTL]
        );
        $driver->execute(
            'INSERT INTO `{login_attempts}` (ip, attempted) VALUES (:ip, :time)',
            ['ip' => $ip, 'time' => $time]
        );
    }

    public function countFailedAttempts(?string $ip, int $since): int {
        return $this->toInt($this->getDriver()->getFirstScalarResult(
            'SELECT COUNT(*) FROM `{login_attempts}` WHERE attempted >= :since'
            . (($ip !== null) ? ' AND ip = :ip' : ''),
            ['since' => $since, ...(($ip !== null) ? ['ip' => $ip] : [])]
        ));
    }

    public function clearFailedAttempts(string $ip): void {
        $this->getDriver()->execute('DELETE FROM `{login_attempts}` WHERE ip = :ip', ['ip' => $ip]);
    }

    protected function getOption(string $name): ?string {
        $value = $this->getDriver()->getFirstScalarResult(
            'SELECT value FROM `{options}` WHERE name = :name ORDER BY id DESC',
            ['name' => $name]
        );

        return ($value !== false && is_scalar($value)) ? (string)$value : null;
    }

    protected function setOption(string $name, string $value): void {
        $driver = $this->getDriver();
        $driver->execute('DELETE FROM `{options}` WHERE name = :name', ['name' => $name]);
        $driver->execute('INSERT INTO `{options}` (name, value) VALUES (:name, :value)', ['name' => $name, 'value' => $value]);
    }

    /**
     * A database gives the numbers as strings (e.g. MySQL) or as integers (e.g. SQLite).
     */
    protected function toInt(mixed $value): int {
        return is_numeric($value) ? (int)$value : 0;
    }

    protected function getDriver(): IDataStorageDriver {
        return Container::getInstance()->getDataStorageDriver();
    }
}
