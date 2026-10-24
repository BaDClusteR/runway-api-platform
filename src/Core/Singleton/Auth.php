<?php

declare(strict_types=1);

namespace ApiPlatform\Core\Singleton;

use ApiPlatform\Core\Auth\DTO\CredentialsDTO;
use ApiPlatform\Core\Auth\DTO\SessionDTO;
use ApiPlatform\Core\Auth\IAuthStorage;
use ApiPlatform\Exception\BadRequestException;
use ApiPlatform\Exception\TooManyRequestsException;

/**
 * The password is stored as a hash (Argon2id if PHP has it), the sessions by the hashes of their tokens: the data
 * storage does not reveal them. The failed logins are limited per IP address and for all the addresses together.
 */
class Auth implements IAuth {
    /**
     * The bytes of a session token: it's a hex string of twice as many characters.
     *
     * @var positive-int
     */
    protected const int TOKEN_BYTES = 32;

    public const int MIN_PASSWORD_LENGTH = 10;

    /** The failed logins are counted for this period, in seconds. */
    protected const int ATTEMPTS_PERIOD = 900;

    protected const int MAX_ATTEMPTS_PER_IP = 5;

    /** A distributed attack comes from many addresses. */
    protected const int MAX_ATTEMPTS = 50;

    /** The last activity of a session is updated not more often, in seconds. */
    protected const int TOUCH_INTERVAL = 60;

    /** The hash checked when there are no credentials: it's made once. */
    protected static ?string $dummyHash = null;

    /**
     * @param mixed $enabled    API_AUTH_ENABLED: false makes every endpoint public (e.g. the access is limited by the
     *                          web server). Enabled if not set, empty or invalid: only an explicit "false" (0, "off",
     *                          "no") opens the API. The ENV values are typed by the parser ("true" is a bool, an empty
     *                          value is ""), so it's normalized.
     * @param int   $sessionTtl In seconds.
     */
    public function __construct(
        protected IAuthStorage $storage,
        protected mixed        $enabled = true,
        protected int          $sessionTtl = 30 * 86400
    ) {}

    public function isEnabled(): bool {
        if ($this->enabled === null || $this->enabled === '') {
            return true;
        }

        return filter_var($this->enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }

    public function hasCredentials(): bool {
        return $this->storage->getCredentials() !== null;
    }

    public function logIn(string $login, string $password, string $ip, string $userAgent): ?SessionDTO {
        $now = time();
        $since = $now - static::ATTEMPTS_PERIOD;

        if (
            $this->storage->countFailedAttempts($ip, $since) >= static::MAX_ATTEMPTS_PER_IP
            || $this->storage->countFailedAttempts(null, $since) >= static::MAX_ATTEMPTS
        ) {
            throw new TooManyRequestsException('Too many failed logins: try again later.');
        }

        $credentials = $this->storage->getCredentials();

        // The hash is checked anyway: the response time does not tell whether the credentials are set.
        $isPasswordCorrect = password_verify($password, ($credentials !== null) ? $credentials->passwordHash : $this->getDummyHash());

        if (!$credentials || !$isPasswordCorrect || !hash_equals($credentials->login, $login)) {
            $this->storage->addFailedAttempt($ip, $now);

            return null;
        }

        $this->storage->clearFailedAttempts($ip);

        // E.g. PHP got a stronger algorithm.
        if (password_needs_rehash($credentials->passwordHash, $this->getAlgorithm())) {
            $this->storage->setCredentials(new CredentialsDTO($login, password_hash($password, $this->getAlgorithm())));
        }

        $token = bin2hex(random_bytes(static::TOKEN_BYTES));
        $validUntil = $now + $this->sessionTtl;
        $this->storage->addSession($this->hashToken($token), $validUntil, $ip, mb_substr($userAgent, 0, 500));

        return new SessionDTO($token, $validUntil);
    }

    public function isAuthenticated(string $token): bool {
        if ($token === '') {
            return false;
        }

        $session = $this->storage->findSession($this->hashToken($token));
        $now = time();

        if (!$session || !$session->isActive || $session->validUntil < $now) {
            return false;
        }

        if ($now - $session->lastActive >= static::TOUCH_INTERVAL) {
            $this->storage->touchSession($session->id, $now);
        }

        return true;
    }

    public function logOut(string $token): void {
        if ($token !== '') {
            $this->storage->deactivateSession($this->hashToken($token));
        }
    }

    public function setCredentials(string $login, string $password): void {
        $login = trim($login);

        if ($login === '') {
            throw new BadRequestException('The login is empty.');
        }

        if (mb_strlen($password) < static::MIN_PASSWORD_LENGTH) {
            throw new BadRequestException('The password is shorter than ' . static::MIN_PASSWORD_LENGTH . ' characters.');
        }

        $this->storage->setCredentials(new CredentialsDTO($login, password_hash($password, $this->getAlgorithm())));
        $this->storage->deactivateAllSessions();
    }

    /**
     * The tokens are random, so a fast hash is enough.
     */
    protected function hashToken(string $token): string {
        return hash('sha256', $token);
    }

    protected function getAlgorithm(): string {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    }

    protected function getDummyHash(): string {
        return static::$dummyHash ??= password_hash(random_bytes(16), $this->getAlgorithm());
    }
}
