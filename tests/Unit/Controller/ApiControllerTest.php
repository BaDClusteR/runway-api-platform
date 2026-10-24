<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Unit\Controller;

use ApiPlatform\Controller\ApiController;
use ApiPlatform\Controller\System\ApiExceptionResponseController;
use ApiPlatform\Controller\System\ApiResponseController;
use ApiPlatform\Core\Provider\AllowedOrigins\AllowedOriginsProvider;
use ApiPlatform\Core\Provider\Endpoints\EndpointsProvider;
use ApiPlatform\Core\Storage\SessionCookie;
use ApiPlatform\Core\Storage\TokenStorage;
use ApiPlatform\DTO\ApiEndpointDTO;
use ApiPlatform\DTO\ApiRequestDTO;
use ApiPlatform\DTO\ApiResponseDTO;
use ApiPlatform\IApi;
use ApiPlatform\Tests\Fixture\ChatEndpoints;
use ApiPlatform\Tests\Support\EnvStub;
use ApiPlatform\Tests\Support\LoggerSpy;
use ApiPlatform\Tests\Support\TestRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Runway\Request\Parameters\DTO\FileDTO;
use Runway\Request\Request;
use Runway\Request\Response;
use Runway\Router\Compiler\RouteCompiler;
use Runway\Router\DTO\RouteMatchDTO;
use Runway\Service\DTO\RouteDTO;
use Runway\Singleton\IKernel;

class ApiControllerTest extends TestCase {
    protected LoggerSpy $logger;

    /** The calls of the API: the endpoint and the request. */
    protected ?ApiRequestDTO $apiRequest = null;

    protected ?ApiEndpointDTO $apiEndpoint = null;

    protected function setUp(): void {
        $this->logger = new LoggerSpy();
    }

    public function testEndpointIsCalled(): void {
        $file = new FileDTO('a.txt', 'text/plain', 1, '/tmp/php1', '0', 'a.txt');
        $request = $this->request('PATCH', 'updateMeta')
            ->setBody('{"meta": {"tag": "work"}}')
            ->setFiles(['attachment' => $file]);

        $response = $this->handle($request, chatId: '42', messageId: '7');

        self::assertSame(200, $response->getCode());
        self::assertSame(['ok' => true], json_decode($response->getBody(), true));
        self::assertSame('updateMeta', $this->apiEndpoint?->method);
        self::assertEquals(
            new ApiRequestDTO('PATCH', ['chatId' => '42', 'messageId' => '7'], ['meta' => ['tag' => 'work']], ['attachment' => $file]),
            $this->apiRequest
        );
    }

    public function testEmptyBody(): void {
        $this->handle($this->request('PATCH', 'updateMeta'));

        self::assertSame([], $this->apiRequest?->body);
    }

    public function testJsonArrayBody(): void {
        $this->handle($this->request('PATCH', 'updateMeta')->setBody('[1, 2]'));

        self::assertSame([1, 2], $this->apiRequest?->body);
    }

    /**
     * Only the routing details are logged: the body can contain passwords.
     */
    public function testRequestIsLogged(): void {
        $this->handle($this->request('PATCH', 'updateMeta')->setPath('/api/chats/42/messages/7/meta')->setBody('{"password": "secret"}'));

        $records = $this->logger->getRecords('debug');
        self::assertNotEmpty($records);
        $record = $records[0];

        self::assertSame('API request', $record['message']);
        self::assertSame(
            ['method' => 'PATCH', 'path' => '/api/chats/42/messages/7/meta', 'endpoint' => ChatEndpoints::class . '::updateMeta'],
            $record['context']
        );
    }

    #[DataProvider('invalidBodyProvider')]
    public function testInvalidBody(string $body, string $message): void {
        $response = $this->handle($this->request('PATCH', 'updateMeta')->setBody($body));

        self::assertSame(422, $response->getCode());
        self::assertSame($message, $this->getErrors($response)[0]['message'] ?? null);
        self::assertNull($this->apiRequest);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidBodyProvider(): array {
        return [
            'not JSON'   => ['{"meta":', 'Request body is not valid JSON'],
            'urlencoded' => ['meta=1', 'Request body is not valid JSON'],
            'scalar'     => ['"meta"', 'Request body is not valid JSON object or array'],
            'number'     => ['42', 'Request body is not valid JSON object or array'],
        ];
    }

    public function testNoRoute(): void {
        $response = $this->handle(TestRequest::create('GET', '/api/chats'));

        self::assertSame(404, $response->getCode());
        self::assertSame('[GET] /api/chats is not valid API endpoint.', $this->getErrors($response)[0]['message'] ?? null);
    }

    /**
     * E.g. a route of another provider leads to the controller.
     */
    public function testRouteIsNotEndpoint(): void {
        $request = TestRequest::create('GET', '/api/chats')
            ->setRouteMatch(new RouteMatchDTO(new RouteDTO('other.route', '/api/chats', '', '', 0, '', ''), []));

        self::assertSame(404, $this->handle($request)->getCode());
    }

    /**
     * A page of another site can send a request from the browser of the user (CSRF): with the session cookie, or with
     * the access given by the web server (e.g. by the IP address with API_AUTH_ENABLED=false).
     *
     * @param array<string, string> $headers
     * @param array<string, string> $cookies
     */
    #[DataProvider('originProvider')]
    public function testOrigin(string $method, array $headers, array $cookies, int $expectedCode): void {
        $request = $this->request($method, 'updateMeta', ['host' => 'api.example:8443', ...$headers])->setCookies($cookies);

        self::assertSame($expectedCode, $this->handle($request)->getCode());
    }

    /**
     * @return array<string, array{string, array<string, string>, array<string, string>, int}>
     */
    public static function originProvider(): array {
        $cookie = ['api_session' => 'token'];

        return [
            // A script.
            'no origin'                      => ['PATCH', [], $cookie, 200],
            'same site with cookie'          => ['PATCH', ['origin' => 'https://api.example:8443'], $cookie, 200],
            'same site in other case'        => ['PATCH', ['origin' => 'https://API.example:8443'], $cookie, 200],
            // E.g. TLS ends at a proxy.
            'same site over http'            => ['PATCH', ['origin' => 'http://api.example:8443'], $cookie, 200],
            'other port'                     => ['PATCH', ['origin' => 'https://api.example'], $cookie, 403],
            'other site with cookie'         => ['PATCH', ['origin' => 'https://evil.example'], $cookie, 403],
            'other site without cookie'      => ['PATCH', ['origin' => 'https://evil.example'], [], 403],
            'sandboxed page'                 => ['PATCH', ['origin' => 'null'], [], 403],
            'host is a suffix'               => ['PATCH', ['origin' => 'https://evil-api.example:8443'], [], 403],
            'allowed site without cookie'    => ['PATCH', ['origin' => 'https://app.example'], [], 200],
            // The cookie is for the pages of the API site.
            'allowed site with cookie'       => ['PATCH', ['origin' => 'https://app.example'], $cookie, 403],
            // A page of another site cannot send the header without CORS.
            'other site with token header'   => ['PATCH', ['origin' => 'https://evil.example', 'x-token' => 't'], [], 200],
            'token header and cookie'        => ['PATCH', ['origin' => 'https://evil.example', 'x-token' => 't'], $cookie, 200],
            'safe method of other site'      => ['GET', ['origin' => 'https://evil.example'], $cookie, 200],
            'lowercase safe method'          => ['get', ['origin' => 'https://evil.example'], $cookie, 200],
            'HEAD of other site'             => ['HEAD', ['origin' => 'https://evil.example'], $cookie, 200],
            'OPTIONS of other site'          => ['OPTIONS', ['origin' => 'https://evil.example'], $cookie, 200],
            'POST of other site'             => ['POST', ['origin' => 'https://evil.example'], [], 403],
            'DELETE of other site'           => ['DELETE', ['origin' => 'https://evil.example'], [], 403],
        ];
    }

    public function testOriginError(): void {
        $request = $this->request('POST', 'updateMeta', ['origin' => 'https://evil.example']);

        $response = $this->handle($request);

        self::assertSame([['code' => 403, 'message' => 'The request is made by another site.']], $this->getErrors($response));
        self::assertNull($this->apiRequest);
    }

    /**
     * The host of the request can be in another case.
     */
    public function testHostCase(): void {
        $request = $this->request('PATCH', 'updateMeta', ['origin' => 'https://api.example:8443'])
            ->setHost('API.Example:8443')
            ->setCookies(['api_session' => 'token']);

        self::assertSame(200, $this->handle($request)->getCode());
    }

    /**
     * A sandboxed page has the "null" origin: it has no host even if the request has none.
     */
    public function testOriginWithoutHost(): void {
        $request = $this->request('PATCH', 'updateMeta', ['origin' => 'null'])->setHost('');

        self::assertSame(403, $this->handle($request)->getCode());
    }

    /**
     * The router passes the placeholders by name.
     */
    public function testPositionalPathParametersAreIgnored(): void {
        $this->handle($this->request('PATCH', 'updateMeta'), 'https://app.example', '42', '7');

        self::assertSame([], $this->apiRequest?->pathParameters);
    }

    /**
     * The JSON nesting is limited: 511 arrays in each other (the depth of 512 includes the values).
     */
    public function testBodyDepth(): void {
        $body = str_repeat('[', 511) . str_repeat(']', 511);

        self::assertSame(200, $this->handle($this->request('PATCH', 'updateMeta')->setBody($body))->getCode());
        self::assertSame(422, $this->handle($this->request('PATCH', 'updateMeta')->setBody("[$body]"))->getCode());
    }

    public function testAnyAllowedSite(): void {
        $request = $this->request('POST', 'updateMeta', ['origin' => 'https://any.example']);

        self::assertSame(200, $this->handle($request, allowedOrigins: '*')->getCode());
    }

    /**
     * @param array<string, string> $headers
     */
    protected function request(string $method, string $endpointMethod, array $headers = []): Request {
        $endpoint = array_find(
            new EndpointsProvider(new RouteCompiler(), [ChatEndpoints::class])->getApiEndpoints(),
            static fn(ApiEndpointDTO $endpoint): bool => $endpoint->method === $endpointMethod
        );
        self::assertNotNull($endpoint);

        return TestRequest::create($method, '/api/' . $endpoint->path, $headers)
            ->setHost('api.example:8443')
            ->setRouteMatch(new RouteMatchDTO(new RouteDTO($endpoint->routeName, $endpoint->path, '', '', 0, '', ''), []));
    }

    protected function handle(Request $request, string $allowedOrigins = 'https://app.example', string ...$pathParameters): Response {
        $api = $this->createStub(IApi::class);
        $api->method('execute')->willReturnCallback(function (ApiEndpointDTO $endpoint, ApiRequestDTO $apiRequest): ApiResponseDTO {
            $this->apiEndpoint = $endpoint;
            $this->apiRequest = $apiRequest;

            return new ApiResponseDTO(['ok' => true]);
        });

        $allowedOriginsProvider = new AllowedOriginsProvider(new EnvStub(['API_ALLOW_ORIGIN' => $allowedOrigins]));
        $responseController = new ApiResponseController(
            new EnvStub(),
            new Response(),
            $this->logger,
            $request,
            new SessionCookie($request),
            $allowedOriginsProvider
        );

        return new ApiController(
            $request,
            $api,
            new EndpointsProvider(new RouteCompiler(), [ChatEndpoints::class]),
            $responseController,
            new ApiExceptionResponseController($responseController, $this->logger, $request, $this->createStub(IKernel::class)),
            $this->logger,
            new TokenStorage($request),
            $allowedOriginsProvider
        )->handle(...$pathParameters);
    }

    /**
     * @return array<array{code: int, message: string}>
     */
    protected function getErrors(Response $response): array {
        $body = json_decode($response->getBody(), true);
        self::assertIsArray($body);
        self::assertIsArray($body['errors']);

        /** @var array<array{code: int, message: string}> */
        return $body['errors'];
    }
}
