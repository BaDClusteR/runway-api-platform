<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Unit\Controller;

use ApiPlatform\Controller\Controller404Decorator;
use ApiPlatform\Controller\ExceptionControllerDecorator;
use ApiPlatform\Controller\MethodNotAllowedControllerDecorator;
use ApiPlatform\Controller\System\ApiExceptionResponseController;
use ApiPlatform\Controller\System\ApiResponseController;
use ApiPlatform\Core\Provider\AllowedOrigins\AllowedOriginsProvider;
use ApiPlatform\Core\Storage\SessionCookie;
use ApiPlatform\Tests\Support\EnvStub;
use ApiPlatform\Tests\Support\LoggerSpy;
use ApiPlatform\Tests\Support\TestRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Runway\Controller\IController404;
use Runway\Controller\IExceptionController;
use Runway\Controller\IMethodNotAllowedController;
use Runway\Request\Request;
use Runway\Request\Response;
use Runway\Singleton\IKernel;

/**
 * The JSON errors of the API paths, the default responses of the others.
 */
class DecoratorsTest extends TestCase {
    #[DataProvider('pathProvider')]
    public function testNotFound(string $path, string $prefix, bool $isApi): void {
        $inner = $this->createStub(IController404::class);
        $inner->method('run')->willReturn(new Response(404, 'Not found'));
        $request = TestRequest::create('GET', $path);

        $response = new Controller404Decorator($inner, $request, $this->errorController($request), $prefix)->run();

        self::assertSame(404, $response->getCode());

        if ($isApi) {
            self::assertSame('application/json', $response->getHeaders()['Content-Type'] ?? null);
            self::assertSame(
                [['code' => 404, 'message' => "[GET] $path is not valid API endpoint."]],
                $this->getErrors($response)
            );
        } else {
            self::assertSame('Not found', $response->getBody());
        }
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function pathProvider(): array {
        return [
            'endpoint'           => ['/api/chats', '/api', true],
            'prefix'             => ['/api', '/api', true],
            'prefix with slash'  => ['/api/', '/api', true],
            'slash of prefix'    => ['/api/chats', '/api/', true],
            'other prefix'       => ['/v2/chats', '/v2', true],
            'page'               => ['/chats', '/api', false],
            'similar path'       => ['/apis/chats', '/api', false],
            'prefix in the path' => ['/docs/api/chats', '/api', false],
        ];
    }

    public function testDefaultPrefix(): void {
        $request = TestRequest::create('GET', '/api/chats');

        $response = new Controller404Decorator($this->createStub(IController404::class), $request, $this->errorController($request))
            ->run();

        self::assertSame('application/json', $response->getHeaders()['Content-Type'] ?? null);
    }

    public function testException(): void {
        $inner = $this->createStub(IExceptionController::class);
        $inner->method('run')->willReturn(new Response(500, 'Error page'));

        $request = TestRequest::create('GET', '/api/chats');
        $response = new ExceptionControllerDecorator($inner, $request, $this->errorController($request))
            ->run(new RuntimeException('The details'));

        self::assertSame(500, $response->getCode());
        self::assertSame([['code' => 500, 'message' => 'Internal server error']], $this->getErrors($response));

        $request = TestRequest::create('GET', '/chats');
        $response = new ExceptionControllerDecorator($inner, $request, $this->errorController($request), '/api')
            ->run(new RuntimeException('The details'));

        self::assertSame('Error page', $response->getBody());
    }

    public function testMethodNotAllowed(): void {
        $request = TestRequest::create('DELETE', '/api/chats/42');

        $response = $this->methodNotAllowedDecorator($request)->run(['GET', 'HEAD']);

        self::assertSame(405, $response->getCode());
        self::assertSame('GET, HEAD, OPTIONS', $response->getHeaders()['Allow']);
        self::assertSame(
            [[
                'code'    => 405,
                'message' => '[DELETE] /api/chats/42: the method is not allowed, allowed methods: GET, HEAD, OPTIONS.',
            ]],
            $this->getErrors($response)
        );
    }

    /**
     * E.g. a CORS preflight: the allowed methods (and the CORS headers for an allowed origin).
     */
    #[DataProvider('optionsMethodProvider')]
    public function testOptions(string $method): void {
        $request = TestRequest::create($method, '/api/chats/42', ['origin' => 'https://app.example']);

        $response = $this->methodNotAllowedDecorator($request)->run(['GET', 'OPTIONS', 'PATCH']);

        self::assertSame(204, $response->getCode());
        self::assertSame('', $response->getBody());
        self::assertSame('GET, OPTIONS, PATCH', $response->getHeaders()['Allow']);
        self::assertSame('https://app.example', $response->getHeaders()['Access-Control-Allow-Origin']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function optionsMethodProvider(): array {
        return [
            'upper case' => ['OPTIONS'],
            'lower case' => ['options'],
        ];
    }

    public function testMethodNotAllowedOfPage(): void {
        $request = TestRequest::create('DELETE', '/chats/42');
        $inner = $this->createMock(IMethodNotAllowedController::class);
        $inner->expects($this->once())->method('run')->with(['GET'])->willReturn(new Response(405, 'Not allowed'));

        $response = $this->methodNotAllowedDecorator($request, $inner)->run(['GET']);

        self::assertSame('Not allowed', $response->getBody());
    }

    protected function methodNotAllowedDecorator(
        Request                      $request,
        ?IMethodNotAllowedController $inner = null
    ): MethodNotAllowedControllerDecorator {
        return new MethodNotAllowedControllerDecorator(
            $inner ?? $this->createStub(IMethodNotAllowedController::class),
            $request,
            $this->responseController($request),
            $this->errorController($request)
        );
    }

    protected function responseController(Request $request): ApiResponseController {
        return new ApiResponseController(
            new EnvStub(),
            new Response(),
            new LoggerSpy(),
            $request,
            new SessionCookie($request),
            new AllowedOriginsProvider(new EnvStub(['API_ALLOW_ORIGIN' => 'https://app.example']))
        );
    }

    protected function errorController(Request $request): ApiExceptionResponseController {
        return new ApiExceptionResponseController(
            $this->responseController($request),
            new LoggerSpy(),
            $request,
            $this->createStub(IKernel::class)
        );
    }

    /**
     * @return array<mixed>
     */
    protected function getErrors(Response $response): array {
        $body = json_decode($response->getBody(), true);
        self::assertIsArray($body);
        self::assertIsArray($body['errors']);

        return $body['errors'];
    }
}
