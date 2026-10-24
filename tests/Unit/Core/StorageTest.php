<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Unit\Core;

use ApiPlatform\Core\Provider\AllowedOrigins\AllowedOriginsProvider;
use ApiPlatform\Core\Storage\SessionCookie;
use ApiPlatform\Core\Storage\TokenStorage;
use ApiPlatform\Tests\Support\EnvStub;
use ApiPlatform\Tests\Support\TestRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The token of the request, the session cookie of the response, the allowed origins.
 */
class StorageTest extends TestCase {
    protected function tearDown(): void {
        // The change is static (shared by the instances).
        new SessionCookie(TestRequest::create())->takeChange();
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, string> $cookies
     */
    #[DataProvider('tokenProvider')]
    public function testToken(array $headers, array $cookies, ?string $tokenHeader, string $token, bool $isCookie): void {
        $request = TestRequest::create(headers: $headers)->setCookies($cookies);
        $storage = new TokenStorage($request, $tokenHeader, 'session');

        self::assertSame($token, $storage->getToken());
        self::assertSame($isCookie, $storage->isCookieToken());
    }

    /**
     * @return array<string, array{array<string, string>, array<string, string>, string|null, string, bool}>
     */
    public static function tokenProvider(): array {
        return [
            'none'                => [[], [], null, '', false],
            'header'              => [['x-token' => 'h'], [], null, 'h', false],
            'cookie'              => [[], ['session' => 'c'], null, 'c', true],
            // A script can have a cookie of a browser session.
            'header beats cookie' => [['x-token' => 'h'], ['session' => 'c'], null, 'h', false],
            'empty header'        => [['x-token' => ''], ['session' => 'c'], null, 'c', true],
            'custom header'       => [['authorization-token' => 'h'], [], 'Authorization-Token', 'h', false],
            'default header'      => [['x-token' => 'h'], [], '', 'h', false],
            'other cookie'        => [[], ['api_session' => 'c'], null, '', false],
        ];
    }

    public function testDefaultCookieName(): void {
        $request = TestRequest::create()->setCookies(['api_session' => 'c']);

        self::assertSame('c', new TokenStorage($request)->getToken());
    }

    public function testSessionCookie(): void {
        $cookie = new SessionCookie(TestRequest::create()->setProtocol('https'), 'session');

        $cookie->set('token', 1900000000);
        $change = $cookie->takeChange();

        self::assertNotNull($change);
        self::assertSame('session', $change->getName());
        self::assertSame('token', $change->getValue());
        self::assertSame(1900000000, $change->getExpires());
        self::assertSame('/', $change->getPath());
        self::assertSame('', $change->getDomain());
        self::assertTrue($change->isSecure());
        self::assertTrue($change->isHttpOnly());
        self::assertSame('Strict', $change->getSameSite());
        // Sent once.
        self::assertNull($cookie->takeChange());
    }

    public function testSessionCookieOverHttp(): void {
        $cookie = new SessionCookie(TestRequest::create()->setProtocol('http'));

        $cookie->set('token', 1900000000);

        self::assertFalse($cookie->takeChange()?->isSecure());
    }

    public function testDeletedSessionCookie(): void {
        $cookie = new SessionCookie(TestRequest::create());

        $cookie->delete();
        $change = $cookie->takeChange();

        self::assertSame('api_session', $change?->getName());
        self::assertSame('', $change->getValue());
        // In the past.
        self::assertSame(1, $change->getExpires());
    }

    /**
     * The endpoint sets the cookie, the response controller (another instance) sends it.
     */
    public function testChangeIsShared(): void {
        new SessionCookie(TestRequest::create())->set('token', 1900000000);

        self::assertSame('token', new SessionCookie(TestRequest::create())->takeChange()?->getValue());
    }

    public function testNoChange(): void {
        self::assertNull(new SessionCookie(TestRequest::create())->takeChange());
    }

    /**
     * @param string[] $expected
     */
    #[DataProvider('allowedOriginsProvider')]
    public function testAllowedOrigins(mixed $value, array $expected, bool $isAnyAllowed): void {
        $provider = new AllowedOriginsProvider(new EnvStub(['API_ALLOW_ORIGIN' => $value]));

        self::assertSame($expected, $provider->getAllowedOrigins());
        self::assertSame($isAnyAllowed, $provider->isAnyAllowed());
    }

    /**
     * @return array<string, array{mixed, string[], bool}>
     */
    public static function allowedOriginsProvider(): array {
        return [
            'not set'         => [null, [], false],
            'empty'           => ['', [], false],
            'one'             => ['https://app.example', ['https://app.example'], false],
            'several'         => [
                ' https://app.example/ ,, https://admin.example:8443',
                ['https://app.example', 'https://admin.example:8443'],
                false,
            ],
            'any'             => ['*', ['*'], true],
            'any among others' => ['https://app.example, *', ['https://app.example', '*'], true],
            // The parser types the values.
            'not a string'    => [true, [], false],
        ];
    }

    #[DataProvider('isAllowedProvider')]
    public function testIsAllowed(string $allowed, string $origin, bool $expected): void {
        self::assertSame(
            $expected,
            new AllowedOriginsProvider(new EnvStub(['API_ALLOW_ORIGIN' => $allowed]))->isAllowed($origin)
        );
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function isAllowedProvider(): array {
        return [
            'allowed'           => ['https://app.example, https://admin.example', 'https://admin.example', true],
            'other'             => ['https://app.example', 'https://evil.example', false],
            'subdomain'         => ['https://app.example', 'https://evil.app.example', false],
            'other scheme'      => ['https://app.example', 'http://app.example', false],
            'any'               => ['*', 'https://evil.example', true],
            'no origin'         => ['*', '', false],
            'nothing allowed'   => ['', 'https://app.example', false],
        ];
    }
}
