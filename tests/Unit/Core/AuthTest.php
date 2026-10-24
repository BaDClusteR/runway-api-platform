<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Unit\Core;

use ApiPlatform\Core\Auth\DTO\CredentialsDTO;
use ApiPlatform\Core\Auth\DTO\SessionDTO;
use ApiPlatform\Core\Auth\DTO\StoredSessionDTO;
use ApiPlatform\Core\Singleton\Auth;
use ApiPlatform\Exception\BadRequestException;
use ApiPlatform\Exception\TooManyRequestsException;
use ApiPlatform\Tests\Fixture\InMemoryAuthStorage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase {
    protected const string PASSWORD = 'correct horse battery';

    protected const string IP = '203.0.113.5';

    /**
     * The ENV values are typed by the parser.
     */
    #[DataProvider('enabledProvider')]
    public function testIsEnabled(mixed $value, bool $expected): void {
        self::assertSame($expected, new Auth(new InMemoryAuthStorage(), $value)->isEnabled());
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function enabledProvider(): array {
        return [
            'true'          => [true, true],
            'false'         => [false, false],
            'zero'          => [0, false],
            'one'           => [1, true],
            'string false'  => ['false', false],
            'string off'    => ['off', false],
            'not set'       => [null, true],
            // A typo must not open the API.
            'invalid value' => ['flase', true],
            'empty value'   => ['', true],
        ];
    }

    public function testEnabledByDefault(): void {
        self::assertTrue(new Auth(new InMemoryAuthStorage())->isEnabled());
    }

    /**
     * The hash checked without the credentials is made once: the hashing is slow on purpose.
     */
    public function testDummyHashIsMadeOnce(): void {
        $auth = new class (new InMemoryAuthStorage()) extends Auth {
            public function dummyHash(): string {
                return $this->getDummyHash();
            }
        };

        $hash = $auth->dummyHash();

        self::assertSame($hash, $auth->dummyHash());
        self::assertFalse(password_needs_rehash($hash, $this->getAlgorithm()));
    }

    public function testHasCredentials(): void {
        self::assertFalse(new Auth(new InMemoryAuthStorage())->hasCredentials());
        self::assertTrue($this->auth()[0]->hasCredentials());
    }

    public function testLogInStartsSession(): void {
        [$auth, $storage] = $this->auth();

        $before = time();
        $session = $this->logIn($auth);

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $session->token);
        self::assertTrue($auth->isAuthenticated($session->token));
        self::assertGreaterThanOrEqual($before + 30 * 86400, $session->validUntil);
        self::assertLessThanOrEqual(time() + 30 * 86400, $session->validUntil);
        // Only the hash is stored.
        self::assertSame([hash('sha256', $session->token)], array_keys($storage->sessions));
        self::assertSame(['ip' => static::IP, 'userAgent' => 'Browser'], $storage->clients[hash('sha256', $session->token)]);
        self::assertSame($session->validUntil, $storage->sessions[hash('sha256', $session->token)]->validUntil);
    }

    public function testEveryLoginHasNewToken(): void {
        [$auth] = $this->auth();

        self::assertNotSame($this->logIn($auth)->token, $this->logIn($auth)->token);
    }

    public function testSessionTtl(): void {
        $storage = new InMemoryAuthStorage();
        $auth = new Auth($storage, true, 60);
        $auth->setCredentials('me', static::PASSWORD);

        $session = $this->logIn($auth);

        self::assertLessThanOrEqual(time() + 60, $session->validUntil);
        self::assertGreaterThanOrEqual(time() + 59, $session->validUntil);
    }

    public function testLongUserAgentIsCut(): void {
        [$auth, $storage] = $this->auth();

        $this->logIn($auth, userAgent: str_repeat('ж', 600));

        self::assertSame(str_repeat('ж', 500), array_values($storage->clients)[0]['userAgent']);
    }

    #[DataProvider('wrongCredentialsProvider')]
    public function testWrongCredentials(string $login, string $password): void {
        [$auth, $storage] = $this->auth();

        self::assertNull($auth->logIn($login, $password, static::IP, ''));
        self::assertSame([static::IP], array_column($storage->attempts, 'ip'));
        self::assertSame([], $storage->sessions);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function wrongCredentialsProvider(): array {
        return [
            'wrong password' => ['me', 'wrong password!'],
            'wrong login'    => ['you', self::PASSWORD],
            'login case'     => ['Me', self::PASSWORD],
        ];
    }

    public function testNoCredentials(): void {
        $storage = new InMemoryAuthStorage();

        self::assertNull(new Auth($storage)->logIn('me', static::PASSWORD, static::IP, ''));
        self::assertCount(1, $storage->attempts);
    }

    public function testTooManyFailedLoginsFromAddress(): void {
        [$auth] = $this->auth();

        for ($i = 0; $i < 4; $i++) {
            $auth->logIn('me', 'wrong password!', static::IP, '');
        }

        // The fifth failure is still checked.
        self::assertNull($auth->logIn('me', 'wrong password!', static::IP, ''));
        // Another address is not blocked.
        self::assertNotNull($auth->logIn('me', static::PASSWORD, '198.51.100.7', ''));

        $this->expectException(TooManyRequestsException::class);
        $this->expectExceptionCode(429);
        // Even the right password.
        $auth->logIn('me', static::PASSWORD, static::IP, '');
    }

    public function testTooManyFailedLoginsFromAllAddresses(): void {
        [$auth, $storage] = $this->auth();

        for ($i = 0; $i < 49; $i++) {
            $storage->addFailedAttempt("198.51.100.$i", time());
        }

        self::assertNull($auth->logIn('me', 'wrong password!', static::IP, ''));

        $this->expectException(TooManyRequestsException::class);
        $auth->logIn('me', static::PASSWORD, '192.0.2.1', '');
    }

    public function testOldFailuresAreNotCounted(): void {
        [$auth, $storage] = $this->auth();

        for ($i = 0; $i < 60; $i++) {
            $storage->addFailedAttempt(static::IP, time() - 901);
        }

        self::assertNotNull($auth->logIn('me', static::PASSWORD, static::IP, ''));
    }

    public function testFailuresOfLastPeriodAreCounted(): void {
        [$auth, $storage] = $this->auth();

        for ($i = 0; $i < 5; $i++) {
            $storage->addFailedAttempt(static::IP, time() - 899);
        }

        $this->expectException(TooManyRequestsException::class);
        $auth->logIn('me', static::PASSWORD, static::IP, '');
    }

    public function testSuccessfulLoginClearsFailuresOfAddress(): void {
        [$auth, $storage] = $this->auth();

        $auth->logIn('me', 'wrong password!', static::IP, '');
        $auth->logIn('me', 'wrong password!', '198.51.100.7', '');
        $this->logIn($auth);

        self::assertSame(['198.51.100.7'], array_column($storage->attempts, 'ip'));
    }

    /**
     * E.g. PHP got a stronger algorithm: the hash is updated with the right password.
     */
    public function testOutdatedHashIsUpdated(): void {
        [$auth, $storage] = $this->auth();
        $storage->credentials = new CredentialsDTO('me', password_hash(static::PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]));

        $this->logIn($auth);

        self::assertNotNull($storage->credentials);
        self::assertFalse(password_needs_rehash($storage->credentials->passwordHash, $this->getAlgorithm()));
        self::assertTrue(password_verify(static::PASSWORD, $storage->credentials->passwordHash));
        self::assertSame('me', $storage->credentials->login);
    }

    public function testCurrentHashIsKept(): void {
        [$auth, $storage] = $this->auth();
        $credentials = $storage->credentials;

        $this->logIn($auth);

        self::assertSame($credentials, $storage->credentials);
    }

    public function testLogOut(): void {
        [$auth] = $this->auth();
        $token = $this->logIn($auth)->token;
        $otherToken = $this->logIn($auth)->token;

        $auth->logOut($token);

        self::assertFalse($auth->isAuthenticated($token));
        self::assertTrue($auth->isAuthenticated($otherToken));
    }

    public function testLogOutWithoutToken(): void {
        [$auth, $storage] = $this->auth();
        $storage->sessions[hash('sha256', '')] = new StoredSessionDTO(1, time() + 60, time(), true);

        $auth->logOut('');

        self::assertTrue($storage->sessions[hash('sha256', '')]->isActive);
    }

    public function testExpiredSession(): void {
        [$auth, $storage] = $this->auth();
        $token = $this->logIn($auth)->token;
        $storage->sessions[hash('sha256', $token)] = new StoredSessionDTO(1, time() - 1, time(), true);

        self::assertFalse($auth->isAuthenticated($token));
    }

    public function testSessionValidUntilNow(): void {
        [$auth, $storage] = $this->auth();
        $token = $this->logIn($auth)->token;
        $storage->sessions[hash('sha256', $token)] = new StoredSessionDTO(1, time() + 1, time(), true);

        self::assertTrue($auth->isAuthenticated($token));
    }

    public function testInactiveSession(): void {
        [$auth, $storage] = $this->auth();
        $token = $this->logIn($auth)->token;
        $storage->sessions[hash('sha256', $token)] = new StoredSessionDTO(1, time() + 60, time(), false);

        self::assertFalse($auth->isAuthenticated($token));
    }

    public function testUnknownToken(): void {
        [$auth, $storage] = $this->auth();
        // Even if the storage has a session of the empty token.
        $storage->sessions[hash('sha256', '')] = new StoredSessionDTO(1, time() + 60, time(), true);

        self::assertFalse($auth->isAuthenticated(''));
        self::assertFalse($auth->isAuthenticated(str_repeat('0', 64)));
    }

    /**
     * The last activity is updated once a minute, not on every request.
     */
    public function testActivityIsUpdated(): void {
        [$auth, $storage] = $this->auth();
        $token = $this->logIn($auth)->token;
        $hash = hash('sha256', $token);

        $auth->isAuthenticated($token);
        self::assertSame([], $this->getTouches($storage));

        $storage->sessions[$hash] = new StoredSessionDTO(7, time() + 60, time() - 59, true);
        $auth->isAuthenticated($token);
        self::assertSame([], $this->getTouches($storage));

        $storage->sessions[$hash] = new StoredSessionDTO(7, time() + 60, time() - 60, true);
        $before = time();
        $auth->isAuthenticated($token);
        $touches = $this->getTouches($storage);
        self::assertCount(1, $touches);
        self::assertSame(7, $touches[0]['id']);
        self::assertGreaterThanOrEqual($before, $touches[0]['time']);
        self::assertLessThanOrEqual(time(), $touches[0]['time']);
    }

    public function testNewCredentialsEndSessions(): void {
        [$auth] = $this->auth();
        $token = $this->logIn($auth)->token;

        $auth->setCredentials('me', 'another long password');

        self::assertFalse($auth->isAuthenticated($token));
        self::assertNull($auth->logIn('me', static::PASSWORD, static::IP, ''));
        self::assertNotNull($auth->logIn('me', 'another long password', static::IP, ''));
    }

    public function testCredentialsAreHashed(): void {
        [, $storage] = $this->auth();

        self::assertNotNull($storage->credentials);
        self::assertSame('me', $storage->credentials->login);
        self::assertFalse(password_needs_rehash($storage->credentials->passwordHash, $this->getAlgorithm()));
        self::assertTrue(password_verify(static::PASSWORD, $storage->credentials->passwordHash));
    }

    public function testLoginIsTrimmed(): void {
        [$auth, $storage] = $this->auth();

        $auth->setCredentials('  admin ', static::PASSWORD);

        self::assertSame('admin', $storage->credentials?->login);
    }

    #[DataProvider('invalidCredentialsProvider')]
    public function testInvalidCredentials(string $login, string $password, string $message): void {
        [$auth, $storage] = $this->auth();
        $credentials = $storage->credentials;

        try {
            $auth->setCredentials($login, $password);
            self::fail('No exception.');
        } catch (BadRequestException $e) {
            self::assertSame([$message], $e->errors);
        }

        self::assertSame($credentials, $storage->credentials);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function invalidCredentialsProvider(): array {
        return [
            'empty login'    => [' ', 'a long password', 'The login is empty.'],
            'short password' => ['me', '123456789', 'The password is shorter than 10 characters.'],
            // In characters, not bytes.
            'short in chars' => ['me', 'пароль123', 'The password is shorter than 10 characters.'],
        ];
    }

    public function testPasswordOfMinimalLength(): void {
        [$auth] = $this->auth();

        $auth->setCredentials('me', 'пароль1234');

        self::assertNotNull($auth->logIn('me', 'пароль1234', static::IP, ''));
    }

    /**
     * @return array{Auth, InMemoryAuthStorage} With the credentials "me" and PASSWORD.
     */
    protected function auth(): array {
        $storage = new InMemoryAuthStorage();
        $auth = new Auth($storage);
        $auth->setCredentials('me', static::PASSWORD);

        return [$auth, $storage];
    }

    protected function logIn(Auth $auth, string $userAgent = 'Browser'): SessionDTO {
        $session = $auth->logIn('me', static::PASSWORD, static::IP, $userAgent);
        self::assertNotNull($session);

        return $session;
    }

    /**
     * The touches made by the calls: not the value narrowed by the assertions before them.
     *
     * @return array<array{id: int, time: int}>
     *
     * @phpstan-impure
     */
    protected function getTouches(InMemoryAuthStorage $storage): array {
        return $storage->touches;
    }

    protected function getAlgorithm(): string {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    }
}
