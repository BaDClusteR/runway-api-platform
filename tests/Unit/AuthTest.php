<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Unit;

use ApiPlatform\Api;
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
use ApiPlatform\Exception\InternalErrorException;
use ApiPlatform\Tests\Fixture\ChatEndpoints;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Runway\Env\Provider\IEnvVariablesProvider;
use Runway\Request\IRequest;
use Runway\Router\Compiler\RouteCompiler;

class AuthTest extends TestCase {
    /**
     * The ENV values are typed by the parser.
     */
    #[DataProvider('enabledProvider')]
    public function testIsEnabled(mixed $value, bool $expected): void {
        self::assertSame($expected, $this->auth($value)->isEnabled());
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

    public function testNotConfiguredKey(): void {
        $this->expectException(InternalErrorException::class);
        $this->expectExceptionMessageMatches('/API_CRYPTO_KEY is not set/');

        $this->auth(true, '')->isCredentialsCorrect('login', 'password');
    }

    public function testNotConfiguredKeyLength(): void {
        $this->expectException(InternalErrorException::class);
        $this->expectExceptionMessageMatches('/API_CRYPTO_KEY_LENGTH is not set/');

        $this->auth(true, 'key', '')->getKeyLength();
    }

    public function testPrivateEndpointIsAccessibleWithoutAuthorization(): void {
        $response = $this->api($this->authStub(false))->execute($this->getPrivateEndpoint(), $this->request());

        self::assertSame(['chatId' => 42], $response->data->result);
    }

    public function testPrivateEndpointRequiresToken(): void {
        $this->expectException(AuthorizationRequiredException::class);

        $this->api($this->authStub(true))->execute($this->getPrivateEndpoint(), $this->request());
    }

    protected function auth(mixed $enabled, mixed $key = null, mixed $keyLength = null): Auth {
        return new Auth($this->createStub(IEnvVariablesProvider::class), $enabled, $key, $keyLength);
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
