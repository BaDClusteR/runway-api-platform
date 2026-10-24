<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Integration;

use ApiPlatform\Core\Singleton\IAuth;
use ApiPlatform\Tests\Support\AuthTables;
use ApiPlatform\Tests\Support\InProcessRequests;
use ApiPlatform\Tests\Support\ArrayPath;
use PHPUnit\Framework\TestCase;
use Runway\DataStorage\IDataStorageDriver;
use Runway\Request\IResponseRead;
use Runway\Singleton\Container;

/**
 * The login, the session and the logout with the services of the container and the database (SQLite).
 */
class AuthFlowTest extends TestCase {
    use ArrayPath;
    use InProcessRequests;

    protected const string PASSWORD = 'correct horse battery';

    protected IDataStorageDriver $driver;

    protected function setUp(): void {
        $this->driver = AuthTables::create();
        $auth = Container::getInstance()->getService(IAuth::class);
        self::assertInstanceOf(IAuth::class, $auth);
        $auth->setCredentials('me', static::PASSWORD);
    }

    /**
     * A browser: the session is in the cookie.
     */
    public function testBrowserSession(): void {
        self::assertSame(401, $this->request('GET', '/api/chats/42/private')->getCode());
        self::assertSame(['isEnabled' => true, 'isAuthenticated' => false], $this->getJson($this->request('GET', '/api/auth/status')));

        $response = $this->request('POST', '/api/auth/login', '{"login": "me", "password": "' . static::PASSWORD . '"}');

        self::assertSame(200, $response->getCode(), $response->getBody());
        self::assertNull(self::at($this->getJson($response), 'token'));
        $token = $this->getCookie($response);

        $cookies = ['api_session' => $token];
        self::assertSame(200, $this->request('GET', '/api/chats/42/private', cookies: $cookies)->getCode());
        self::assertSame(204, $this->request('GET', '/api/auth/check', cookies: $cookies)->getCode());
        self::assertTrue($this->getJson($this->request('GET', '/api/auth/status', cookies: $cookies))['isAuthenticated']);

        // Only the hash is stored.
        self::assertSame(
            [hash('sha256', $token)],
            $this->driver->getColumn('SELECT token FROM `{tokens}` WHERE active = 1', [], 'token')
        );

        $response = $this->request('POST', '/api/auth/logout', cookies: $cookies);

        self::assertSame(204, $response->getCode());
        self::assertSame('', $this->getCookie($response));
        self::assertSame(401, $this->request('GET', '/api/chats/42/private', cookies: $cookies)->getCode());
        self::assertSame(401, $this->request('GET', '/api/auth/check', cookies: $cookies)->getCode());
    }

    /**
     * A script: the token is in the header.
     */
    public function testScriptSession(): void {
        $response = $this->request(
            'POST',
            '/api/auth/login',
            '{"login": "me", "password": "' . static::PASSWORD . '", "useCookie": false}'
        );

        self::assertSame(200, $response->getCode(), $response->getBody());
        self::assertSame([], $response->getCookies());

        $token = self::at($this->getJson($response), 'token');
        self::assertIsString($token);

        $headers = ['X-Token' => $token];
        self::assertSame(200, $this->request('GET', '/api/chats/42/private', headers: $headers)->getCode());

        // Not the cookie of a browser: nothing to delete.
        $response = $this->request('POST', '/api/auth/logout', headers: $headers);
        self::assertSame([], $response->getCookies());
        self::assertSame(401, $this->request('GET', '/api/chats/42/private', headers: $headers)->getCode());
    }

    public function testInvalidCredentials(): void {
        $response = $this->request('POST', '/api/auth/login', '{"login": "me", "password": "wrong password"}');

        self::assertSame(401, $response->getCode());
        self::assertSame([['code' => 401, 'message' => 'Invalid credentials.']], self::at($this->getJson($response), 'errors'));
        self::assertSame([], $response->getCookies());
    }

    public function testEmptyCredentials(): void {
        $response = $this->request('POST', '/api/auth/login', '{"login": "", "password": ""}');

        self::assertSame(400, $response->getCode());
        self::assertSame(
            ['login cannot be empty.', 'password cannot be empty.'],
            array_column(self::arrayAt($this->getJson($response), 'errors'), 'message')
        );
    }

    public function testTooManyFailedLogins(): void {
        for ($i = 0; $i < 5; $i++) {
            $this->request('POST', '/api/auth/login', '{"login": "me", "password": "wrong password"}');
        }

        $response = $this->request('POST', '/api/auth/login', '{"login": "me", "password": "' . static::PASSWORD . '"}');

        self::assertSame(429, $response->getCode());
        self::assertSame('Too many failed logins: try again later.', self::at($this->getJson($response), 'errors', 0, 'message'));
    }

    protected function getCookie(IResponseRead $response): string {
        $cookies = $response->getCookies();
        self::assertCount(1, $cookies);
        self::assertSame('api_session', $cookies[0]->getName());

        return $cookies[0]->getValue();
    }
}
