<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Unit;

use ApiPlatform\Api;
use ApiPlatform\Core\Provider\EndpointMethodParameters\EndpointMethodParametersProvider;
use ApiPlatform\Core\Provider\EndpointMethodParameterValue\EndpointMethodParameterValueProvider;
use ApiPlatform\Core\Provider\Endpoints\EndpointsProvider;
use ApiPlatform\Core\Singleton\IAuth;
use ApiPlatform\Core\Storage\ITokenStorage;
use ApiPlatform\Core\Validator\EndpointMethodParameterValidator;
use ApiPlatform\DTO\ApiEndpointDTO;
use ApiPlatform\DTO\ApiRequestDTO;
use ApiPlatform\Exception\AuthorizationRequiredException;
use ApiPlatform\Exception\BadRequestException;
use ApiPlatform\Exception\InternalErrorException;
use ApiPlatform\Tests\Fixture\ChatEndpoints;
use ApiPlatform\Tests\Fixture\MiscEndpoints;
use ApiPlatform\Tests\Fixture\ResultDTO;
use ApiPlatform\Tests\Fixture\ScalarEndpoints;
use ApiPlatform\Tests\Support\TestRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Runway\Request\Request;
use Runway\Router\Compiler\RouteCompiler;

/**
 * The access, the arguments and the call of an endpoint (the endpoints of the fixtures, see tests/Fixture).
 */
class ApiTest extends TestCase {
    public function testPrivateEndpointIsAccessibleWithoutAuthorization(): void {
        $response = $this->api($this->auth(false, false))->execute($this->endpoint('private'), $this->request());

        self::assertEquals(new ResultDTO(['chatId' => 42]), $response->data);
        self::assertSame(200, $response->code);
        self::assertSame([], $response->headers);
    }

    public function testPrivateEndpointRequiresToken(): void {
        $this->expectException(AuthorizationRequiredException::class);

        $this->api($this->auth(true, false))->execute($this->endpoint('private'), $this->request());
    }

    public function testPrivateEndpointWithToken(): void {
        $auth = $this->createMock(IAuth::class);
        $auth->method('isEnabled')->willReturn(true);
        $auth->expects($this->once())->method('isAuthenticated')->with('the token')->willReturn(true);

        $tokenStorage = $this->createStub(ITokenStorage::class);
        $tokenStorage->method('getToken')->willReturn('the token');

        $response = $this->api($auth, $tokenStorage)->execute($this->endpoint('private'), $this->request());

        self::assertEquals(new ResultDTO(['chatId' => 42]), $response->data);
    }

    /**
     * A public endpoint does not check the token: e.g. no session activity update.
     */
    public function testPublicEndpoint(): void {
        $auth = $this->createMock(IAuth::class);
        $auth->expects($this->never())->method('isAuthenticated');

        $response = $this->api($auth)->execute($this->endpoint('get'), $this->request());

        self::assertEquals(new ResultDTO(['chatId' => 42]), $response->data);
    }

    /**
     * The values of the request are strings: they are cast to the argument types.
     *
     * @param array<string, string> $query
     * @param array<string, mixed> $expected
     */
    #[DataProvider('castProvider')]
    public function testArgumentsAreCast(string $method, array $query, array $expected): void {
        $response = $this->api($this->auth(false, false), request: TestRequest::create()->setGetParameters($query))
            ->execute($this->endpoint($method), $this->request());

        self::assertInstanceOf(ResultDTO::class, $response->data);
        self::assertSame($expected, $response->data->result);
    }

    /**
     * @return array<string, array{string, array<string, string>, array<string, mixed>}>
     */
    public static function castProvider(): array {
        return [
            'int and string' => [
                'messages',
                ['number' => '10', 'direction' => 'after'],
                ['chatId' => 42, 'number' => 10, 'direction' => 'after', 'isInternal' => false],
            ],
            'nullable null'  => [
                'messages',
                [],
                ['chatId' => 42, 'number' => null, 'direction' => 'before', 'isInternal' => false],
            ],
            'int as float'   => [
                'messages',
                ['number' => '1e1'],
                ['chatId' => 42, 'number' => 10, 'direction' => 'before', 'isInternal' => false],
            ],
            'digits string'  => [
                'messages',
                ['direction' => '5'],
                ['chatId' => 42, 'number' => null, 'direction' => '5', 'isInternal' => false],
            ],
            'bool false'     => ['flags', ['pinned' => 'false'], ['chatId' => 42, 'pinned' => false]],
            'bool off'       => ['flags', ['pinned' => 'off'], ['chatId' => 42, 'pinned' => false]],
            'bool true'      => ['flags', ['pinned' => 'true'], ['chatId' => 42, 'pinned' => true]],
            'bool one'       => ['flags', ['pinned' => '1'], ['chatId' => 42, 'pinned' => true]],
            'bool zero'      => ['flags', ['pinned' => '0'], ['chatId' => 42, 'pinned' => false]],
        ];
    }

    public function testFloatAndBoolPlaceholders(): void {
        $response = $this->api($this->auth(false, false))->execute(
            $this->endpoint('point', MiscEndpoints::class),
            new ApiRequestDTO('POST', ['x' => '1.5', 'y' => '2', 'visible' => 'false'], [], [])
        );

        self::assertInstanceOf(ResultDTO::class, $response->data);
        self::assertSame(['x' => 1.5, 'y' => 2.0, 'visible' => false], $response->data->result);
    }

    /**
     * The JSON values have their types: e.g. a number for a string argument is a string.
     *
     * @param array<string, mixed> $body
     * @param array<string, mixed> $expected
     */
    #[DataProvider('jsonCastProvider')]
    public function testJsonValuesAreCast(array $body, array $expected): void {
        $response = $this->api($this->auth(false, false))->execute(
            $this->endpoint('body', MiscEndpoints::class),
            new ApiRequestDTO('POST', [], $body, [])
        );

        self::assertInstanceOf(ResultDTO::class, $response->data);
        self::assertSame($expected, $response->data->result);
    }

    /**
     * @return array<string, array{array<string, mixed>, array<string, mixed>}>
     */
    public static function jsonCastProvider(): array {
        return [
            'as is'          => [['text' => 'a', 'flag' => true, 'ratio' => 0.5], ['text' => 'a', 'flag' => true, 'ratio' => 0.5]],
            'number as text' => [['text' => 123, 'flag' => false, 'ratio' => 2], ['text' => '123', 'flag' => false, 'ratio' => 2.0]],
            'int as flag'    => [['text' => '', 'flag' => 1, 'ratio' => '1.5'], ['text' => '', 'flag' => true, 'ratio' => 1.5]],
            'zero as flag'   => [['text' => true, 'flag' => 0, 'ratio' => 0], ['text' => '1', 'flag' => false, 'ratio' => 0.0]],
        ];
    }

    public function testArrayArgument(): void {
        $response = $this->api($this->auth(false, false))->execute(
            $this->endpoint('updateMeta'),
            new ApiRequestDTO('PATCH', ['chatId' => '42', 'messageId' => '7'], ['meta' => ['tag' => 'work']], [])
        );

        self::assertInstanceOf(ResultDTO::class, $response->data);
        self::assertSame(['chatId' => 42, 'messageId' => 7, 'meta' => ['tag' => 'work']], $response->data->result);
    }

    /**
     * The errors of all the arguments are reported at once.
     */
    public function testValidationErrors(): void {
        try {
            $this->api($this->auth(false, false))->execute(
                $this->endpoint('validated', MiscEndpoints::class),
                new ApiRequestDTO('POST', [], ['code' => 'abcd'], [])
            );
            self::fail('No exception.');
        } catch (BadRequestException $e) {
            self::assertSame(['code should be at most 3 characters long, 4 given.'], $e->errors);
        }

        try {
            $this->api($this->auth(false, false), request: TestRequest::create()->setGetParameters(['count' => '11']))
                ->execute(
                    $this->endpoint('validated', MiscEndpoints::class),
                    new ApiRequestDTO('POST', [], ['code' => ''], [])
                );
            self::fail('No exception.');
        } catch (BadRequestException $e) {
            self::assertSame(['code cannot be empty.', 'count should be 10 or less, 11 given.'], $e->errors);
        }
    }

    public function testValidArguments(): void {
        $response = $this->api($this->auth(false, false), request: TestRequest::create()->setGetParameters(['count' => '10']))
            ->execute($this->endpoint('validated', MiscEndpoints::class), new ApiRequestDTO('POST', [], ['code' => 'abc'], []));

        self::assertInstanceOf(ResultDTO::class, $response->data);
        self::assertSame(['code' => 'abc', 'count' => 10], $response->data->result);
    }

    public function testValidationIsBeforeAccess(): void {
        $this->expectException(AuthorizationRequiredException::class);

        $this->api($this->auth(true, false))->execute(
            $this->endpoint('private'),
            new ApiRequestDTO('GET', ['chatId' => 'abc'], [], [])
        );
    }

    public function testEndpointMustReturnObject(): void {
        $this->expectException(InternalErrorException::class);
        $this->expectExceptionMessage(
            "API endpoint method '" . ScalarEndpoints::class . "::scalar' is expected to return a DTO object, returned string."
        );

        $this->api($this->auth(false, false))->execute($this->endpoint('scalar', ScalarEndpoints::class), $this->request());
    }

    public function testVersion(): void {
        self::assertSame('test', $this->api($this->auth(false, false))->getVersion());
    }

    protected function auth(bool $isEnabled, bool $isAuthenticated): IAuth {
        $auth = $this->createStub(IAuth::class);
        $auth->method('isEnabled')->willReturn($isEnabled);
        $auth->method('isAuthenticated')->willReturn($isAuthenticated);

        return $auth;
    }

    protected function api(IAuth $auth, ?ITokenStorage $tokenStorage = null, ?Request $request = null): Api {
        return new Api(
            new EndpointMethodParametersProvider(
                new EndpointMethodParameterValueProvider($request ?? TestRequest::create())
            ),
            new EndpointMethodParameterValidator(),
            $auth,
            $tokenStorage ?? $this->createStub(ITokenStorage::class),
            'test'
        );
    }

    /**
     * @param class-string $class
     */
    protected function endpoint(string $method, string $class = ChatEndpoints::class): ApiEndpointDTO {
        $endpoint = array_find(
            new EndpointsProvider(new RouteCompiler(), [$class])->getApiEndpoints(),
            static fn(ApiEndpointDTO $endpoint): bool => $endpoint->method === $method
        );
        self::assertNotNull($endpoint);

        return $endpoint;
    }

    protected function request(): ApiRequestDTO {
        return new ApiRequestDTO('GET', ['chatId' => '42'], [], []);
    }
}
