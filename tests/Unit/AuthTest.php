<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Unit;

use ApiPlatform\Api;
use ApiPlatform\Core\Auth\DTO\StoredSessionDTO;
use ApiPlatform\Core\Provider\EndpointMethodParameters\EndpointMethodParametersProvider;
use ApiPlatform\Core\Provider\EndpointMethodParameterValue\EndpointMethodParameterValueProvider;
use ApiPlatform\Core\Provider\Endpoints\EndpointsProvider;
use ApiPlatform\Core\Singleton\Auth;
use ApiPlatform\Core\Singleton\IAuth;
use ApiPlatform\Core\Storage\ITokenStorage;
use ApiPlatform\Core\Validator\EndpointMethodParameterValidator;
use ApiPlatform\DTO\ApiEndpointDTO;
use ApiPlatform\DTO\ApiRequestDTO;
use ApiPlatform\Exception\AuthorizationRequiredException;
use ApiPlatform\Exception\BadRequestException;
use ApiPlatform\Exception\TooManyRequestsException;
use ApiPlatform\Tests\Fixture\ChatEndpoints;
use ApiPlatform\Tests\Fixture\InMemoryAuthStorage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Runway\Request\IRequest;
use Runway\Router\Compiler\RouteCompiler;

class AuthTest extends TestCase {
    protected const string PASSWORD = 'correct horse battery';

    /**
     * The ENV values are typed by the parser.
     */
    #[DataProvider('enabledProvider')]
    public function testIsEnabled(mixed $value, bool $expected): void {
        self::assertSame($expected, new Auth(new InMemoryAuthStorage(), $value)->isEnabled());
    }

    public static function enabledProvider(): array {
        return [
            'true'          => [true, true],
            'false'         => [false, false],
            'zero'          => [0, false],
            'string false'  => ['false', false],
            'not set'       => [null, true],
            // A typo must not open the API.
            'invalid value' => ['flase', true],
        ];
    }

    public function testLogInStartsSession(): void {
        [$auth, $storage] = $this->auth();

        $session = $auth->logIn('me', static::PASSWORD, '203.0.113.5', 'Browser');

        self::assertNotNull($session);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $session->token);
        self::assertTrue($auth->isAuthenticated($session->token));
        // Only the hash is stored.
        self::assertArrayNotHasKey($session->token, $storage->sessions);
        self::assertStringStartsNotWith(static::PASSWORD, $storage->credentials->passwordHash);
    }

    #[DataProvider('wrongCredentialsProvider')]
    public function testWrongCredentials(string $login, string $password): void {
        [$auth, $storage] = $this->auth();

        self::assertNull($auth->logIn($login, $password, '203.0.113.5', ''));
        self::assertCount(1, $storage->attempts);
    }

    public static function wrongCredentialsProvider(): array {
        return [
            'wrong password' => ['me', 'wrong password!'],
            'wrong login'    => ['you', self::PASSWORD],
        ];
    }

    public function testNoCredentials(): void {
        self::assertNull(new Auth(new InMemoryAuthStorage())->logIn('me', static::PASSWORD, '203.0.113.5', ''));
    }

    public function testTooManyFailedLogins(): void {
        [$auth] = $this->auth();

        for ($i = 0; $i < 5; $i++) {
            $auth->logIn('me', 'wrong password!', '203.0.113.5', '');
        }

        // Another address is not blocked.
        self::assertNotNull($auth->logIn('me', static::PASSWORD, '198.51.100.7', ''));

        $this->expectException(TooManyRequestsException::class);
        // Even the right password.
        $auth->logIn('me', static::PASSWORD, '203.0.113.5', '');
    }

    public function testSuccessfulLoginClearsFailures(): void {
        [$auth, $storage] = $this->auth();

        $auth->logIn('me', 'wrong password!', '203.0.113.5', '');
        $auth->logIn('me', static::PASSWORD, '203.0.113.5', '');

        self::assertSame([], $storage->attempts);
    }

    public function testLogOut(): void {
        [$auth] = $this->auth();
        $token = $auth->logIn('me', static::PASSWORD, '203.0.113.5', '')->token;

        $auth->logOut($token);

        self::assertFalse($auth->isAuthenticated($token));
    }

    public function testExpiredSession(): void {
        [$auth, $storage] = $this->auth();
        $token = $auth->logIn('me', static::PASSWORD, '203.0.113.5', '')->token;
        $hash = array_key_first($storage->sessions);
        $storage->sessions[$hash] = new StoredSessionDTO(1, time() - 1, time(), true);

        self::assertFalse($auth->isAuthenticated($token));
    }

    public function testUnknownToken(): void {
        [$auth] = $this->auth();

        self::assertFalse($auth->isAuthenticated(''));
        self::assertFalse($auth->isAuthenticated(str_repeat('0', 64)));
    }

    public function testNewCredentialsEndSessions(): void {
        [$auth] = $this->auth();
        $token = $auth->logIn('me', static::PASSWORD, '203.0.113.5', '')->token;

        $auth->setCredentials('me', 'another long password');

        self::assertFalse($auth->isAuthenticated($token));
        self::assertNull($auth->logIn('me', static::PASSWORD, '203.0.113.5', ''));
        self::assertNotNull($auth->logIn('me', 'another long password', '203.0.113.5', ''));
    }

    public function testShortPassword(): void {
        $this->expectException(BadRequestException::class);

        $this->auth()[0]->setCredentials('me', 'short');
    }

    public function testPrivateEndpointIsAccessibleWithoutAuthorization(): void {
        $response = $this->api($this->authStub(false))->execute($this->getPrivateEndpoint(), $this->request());

        self::assertSame(['chatId' => 42], $response->data->result);
    }

    public function testPrivateEndpointRequiresToken(): void {
        $this->expectException(AuthorizationRequiredException::class);

        $this->api($this->authStub(true))->execute($this->getPrivateEndpoint(), $this->request());
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

    protected function authStub(bool $isEnabled): IAuth {
        $auth = $this->createStub(IAuth::class);
        $auth->method('isEnabled')->willReturn($isEnabled);
        $auth->method('isAuthenticated')->willReturn(false);

        return $auth;
    }

    protected function api(IAuth $auth): Api {
        return new Api(
            new EndpointMethodParametersProvider(
                new EndpointMethodParameterValueProvider($this->createStub(IRequest::class))
            ),
            new EndpointMethodParameterValidator(),
            $auth,
            $this->createStub(ITokenStorage::class),
            'test'
        );
    }

    protected function getPrivateEndpoint(): ApiEndpointDTO {
        $provider = new EndpointsProvider(new RouteCompiler(), [ChatEndpoints::class]);

        return array_find(
            $provider->getApiEndpoints(),
            static fn(ApiEndpointDTO $endpoint): bool => $endpoint->method === 'private'
        );
    }

    protected function request(): ApiRequestDTO {
        return new ApiRequestDTO('GET', ['chatId' => '42'], [], []);
    }
}
