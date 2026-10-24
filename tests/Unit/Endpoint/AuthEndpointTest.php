<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Unit\Endpoint;

use ApiPlatform\Core\Singleton\Auth as AuthService;
use ApiPlatform\Core\Storage\SessionCookie;
use ApiPlatform\Core\Storage\TokenStorage;
use ApiPlatform\Endpoint\Auth;
use ApiPlatform\Endpoint\DTO\Auth\DoneDTO;
use ApiPlatform\Exception\ApiException;
use ApiPlatform\Exception\AuthFailedException;
use ApiPlatform\Exception\AuthorizationRequiredException;
use ApiPlatform\Tests\Fixture\InMemoryAuthStorage;
use ApiPlatform\Tests\Support\TestRequest;
use PHPUnit\Framework\TestCase;
use Runway\Request\Request;

/**
 * The endpoints of the login (with the real Auth and the storage in memory).
 */
class AuthEndpointTest extends TestCase {
    protected const string PASSWORD = 'correct horse battery';

    protected InMemoryAuthStorage $storage;

    protected AuthService $auth;

    protected function setUp(): void {
        $this->storage = new InMemoryAuthStorage();
        $this->auth = new AuthService($this->storage);
        $this->auth->setCredentials('me', static::PASSWORD);
    }

    protected function tearDown(): void {
        new SessionCookie(TestRequest::create())->takeChange();
    }

    public function testStatus(): void {
        $token = $this->logIn();

        $status = $this->endpoint()->status();
        self::assertTrue($status->isEnabled);
        self::assertFalse($status->isAuthenticated);

        self::assertTrue($this->endpoint(headers: ['x-token' => $token])->status()->isAuthenticated);
    }

    public function testStatusWithoutAuthorization(): void {
        $status = $this->endpoint(isEnabled: false)->status();

        self::assertFalse($status->isEnabled);
        self::assertTrue($status->isAuthenticated);
    }

    public function testCheck(): void {
        $token = $this->logIn();

        self::assertEquals(new DoneDTO(), $this->endpoint(cookies: ['api_session' => $token])->check());
        self::assertEquals(new DoneDTO(), $this->endpoint(isEnabled: false)->check());

        $this->expectException(AuthorizationRequiredException::class);
        $this->expectExceptionCode(401);
        $this->endpoint(cookies: ['api_session' => 'wrong'])->check();
    }

    /**
     * A browser gets the session in the cookie.
     */
    public function testLoginWithCookie(): void {
        $result = $this->endpoint()->login('me', static::PASSWORD);

        self::assertNull($result->token);
        $cookie = new SessionCookie(TestRequest::create())->takeChange();
        self::assertNotNull($cookie);
        self::assertTrue($this->auth->isAuthenticated($cookie->getValue()));
        self::assertSame(date(DATE_ATOM, $cookie->getExpires()), $result->validUntil);
    }

    /**
     * A script gets the token.
     */
    public function testLoginWithToken(): void {
        $result = $this->endpoint()->login('me', static::PASSWORD, false);

        self::assertNotNull($result->token);
        self::assertTrue($this->auth->isAuthenticated($result->token));
        self::assertNull(new SessionCookie(TestRequest::create())->takeChange());

        $session = $this->storage->sessions[hash('sha256', $result->token)];
        self::assertSame(date(DATE_ATOM, $session->validUntil), $result->validUntil);
    }

    public function testLoginKeepsClient(): void {
        $request = TestRequest::create('POST', '/api/auth/login', ['user-agent' => 'Browser'])->setIpAddress('198.51.100.7');

        $token = $this->endpoint(request: $request)->login('me', static::PASSWORD, false)->token;

        self::assertSame(['ip' => '198.51.100.7', 'userAgent' => 'Browser'], $this->storage->clients[hash('sha256', (string)$token)]);
    }

    /**
     * A new login ends the current session of the client.
     */
    public function testLoginEndsCurrentSession(): void {
        $token = $this->logIn();

        $this->endpoint(cookies: ['api_session' => $token])->login('me', static::PASSWORD);

        self::assertFalse($this->auth->isAuthenticated($token));
    }

    /**
     * A wrong password (e.g. a typo) does not end the current session.
     */
    public function testFailedLogin(): void {
        $token = $this->logIn();

        try {
            $this->endpoint(cookies: ['api_session' => $token])->login('me', 'wrong password!');
            self::fail('No exception.');
        } catch (AuthFailedException $e) {
            self::assertSame(401, $e->getCode());
            self::assertSame(['Invalid credentials.'], $e->errors);
        }

        self::assertTrue($this->auth->isAuthenticated($token));
        self::assertNull(new SessionCookie(TestRequest::create())->takeChange());
    }

    public function testLoginWithoutAuthorization(): void {
        try {
            $this->endpoint(isEnabled: false)->login('me', static::PASSWORD);
            self::fail('No exception.');
        } catch (ApiException $e) {
            self::assertSame(404, $e->getCode());
            self::assertSame(['The authorization is disabled.'], $e->errors);
        }

        self::assertSame([], $this->storage->sessions);
    }

    public function testLogoutWithCookie(): void {
        $token = $this->logIn();

        self::assertEquals(new DoneDTO(), $this->endpoint(cookies: ['api_session' => $token])->logout());

        self::assertFalse($this->auth->isAuthenticated($token));
        $cookie = new SessionCookie(TestRequest::create())->takeChange();
        self::assertSame('', $cookie?->getValue());
    }

    /**
     * A script has no cookie to delete.
     */
    public function testLogoutWithToken(): void {
        $token = $this->logIn();

        $this->endpoint(headers: ['x-token' => $token])->logout();

        self::assertFalse($this->auth->isAuthenticated($token));
        self::assertNull(new SessionCookie(TestRequest::create())->takeChange());
    }

    protected function logIn(): string {
        $session = $this->auth->logIn('me', static::PASSWORD, '203.0.113.5', '');
        self::assertNotNull($session);

        return $session->token;
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, string> $cookies
     */
    protected function endpoint(
        array    $headers = [],
        array    $cookies = [],
        bool     $isEnabled = true,
        ?Request $request = null
    ): Auth {
        $request ??= TestRequest::create('POST', '/api/auth/login', $headers)->setCookies($cookies);
        $auth = $isEnabled ? $this->auth : new AuthService($this->storage, false);

        return new Auth($auth, new TokenStorage($request), new SessionCookie($request), $request);
    }
}
