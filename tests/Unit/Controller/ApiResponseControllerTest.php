<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Unit\Controller;

use ApiPlatform\Controller\System\ApiResponseController;
use ApiPlatform\Core\Provider\AllowedOrigins\AllowedOriginsProvider;
use ApiPlatform\Core\Storage\SessionCookie;
use ApiPlatform\DTO\ApiResponseDTO;
use ApiPlatform\Endpoint\DTO\Auth\DoneDTO;
use ApiPlatform\Endpoint\DTO\Auth\StatusDTO;
use ApiPlatform\Tests\Support\EnvStub;
use ApiPlatform\Tests\Support\LoggerSpy;
use ApiPlatform\Tests\Support\TestRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Runway\Request\Request;
use Runway\Request\Response;

class ApiResponseControllerTest extends TestCase {
    protected LoggerSpy $logger;

    protected function setUp(): void {
        $this->logger = new LoggerSpy();
    }

    protected function tearDown(): void {
        new SessionCookie(TestRequest::create())->takeChange();
    }

    public function testDtoResponse(): void {
        $response = $this->getResponse(new ApiResponseDTO(new StatusDTO(true, false)));

        self::assertSame(200, $response->getCode());
        self::assertSame("{\n    \"isEnabled\": true,\n    \"isAuthenticated\": false\n}", $response->getBody());
        self::assertSame('application/json', $response->getHeaders()['Content-Type']);
        self::assertSame('request-id', $response->getHeaders()['X-Request-Id']);
        self::assertSame('nosniff', $response->getHeaders()['X-Content-Type-Options']);
        self::assertSame('no-store', $response->getHeaders()['Cache-Control']);
    }

    /**
     * The text is readable: the Unicode and the slashes are not escaped.
     */
    public function testJsonFormat(): void {
        self::assertSame(
            "{\n    \"path\": \"a/b\",\n    \"text\": \"Привет\"\n}",
            $this->getResponse(new ApiResponseDTO(['path' => 'a/b', 'text' => 'Привет']))->getBody()
        );
    }

    public function testInvalidUtf8IsReplaced(): void {
        self::assertSame(
            "{\n    \"text\": \"a\u{FFFD}b\"\n}",
            $this->getResponse(new ApiResponseDTO(['text' => "a\xB1b"]))->getBody()
        );
    }

    /**
     * @param array<mixed>|object $data
     */
    #[DataProvider('emptyDataProvider')]
    public function testNoContent(array|object $data): void {
        $response = $this->getResponse(new ApiResponseDTO($data));

        self::assertSame(204, $response->getCode());
        self::assertSame('', $response->getBody());
    }

    /**
     * @return array<string, array{array<mixed>|object}>
     */
    public static function emptyDataProvider(): array {
        return [
            'empty array' => [[]],
            'empty DTO'   => [new DoneDTO()],
        ];
    }

    public function testCodeOfSuccessfulResponse(): void {
        self::assertSame(201, $this->getResponse(new ApiResponseDTO(['id' => 1], 201))->getCode());
        self::assertSame(204, $this->getResponse(new ApiResponseDTO([], 299))->getCode());
    }

    /**
     * An error without the data keeps its code.
     */
    public function testEmptyError(): void {
        self::assertSame(404, $this->getResponse(new ApiResponseDTO([], 404))->getCode());
        self::assertSame(302, $this->getResponse(new ApiResponseDTO([], 302))->getCode());
        self::assertSame(300, $this->getResponse(new ApiResponseDTO([], 300))->getCode());
        self::assertSame(199, $this->getResponse(new ApiResponseDTO([], 199))->getCode());
    }

    public function testHeaders(): void {
        $headers = $this->getResponse(
            new ApiResponseDTO(['a' => 1], 200, ['Allow' => 'GET', 'X-Request-Id' => 'own', 'Cache-Control' => 'max-age=60'])
        )->getHeaders();

        self::assertSame('GET', $headers['Allow']);
        // The headers of the response win.
        self::assertSame('own', $headers['X-Request-Id']);
        self::assertSame('max-age=60', $headers['Cache-Control']);
    }

    /**
     * The data cannot be encoded: not an empty successful response.
     */
    public function testUnencodableData(): void {
        $response = $this->getResponse(new ApiResponseDTO(['value' => INF]));

        self::assertSame(500, $response->getCode());
        self::assertSame(
            ['errors' => [['code' => 500, 'message' => 'Internal server error']]],
            json_decode($response->getBody(), true)
        );

        $errors = $this->logger->getRecords('error');
        self::assertCount(1, $errors);
        self::assertStringStartsWith(
            ApiResponseController::class . '::getResponseBody: Cannot convert JSON error response to string. Reason: Inf and NaN',
            $errors[0]['message']
        );
        self::assertSame(JSON_ERROR_INF_OR_NAN, $errors[0]['context']['code']);
        self::assertIsString($errors[0]['context']['message']);
        self::assertStringStartsWith('Inf and NaN', $errors[0]['context']['message']);
    }

    /**
     * Without the data: it can contain tokens.
     */
    public function testResponseIsLogged(): void {
        $this->getResponse(new ApiResponseDTO(['token' => 'secret'], 201));

        self::assertSame([['level' => 'debug', 'message' => 'API response', 'context' => ['code' => 201]]], $this->logger->records);
    }

    public function testSessionCookie(): void {
        $request = TestRequest::create();
        new SessionCookie($request)->set('token', 1900000000);

        $cookies = $this->getResponse(new ApiResponseDTO(['a' => 1]), $request)->getCookies();

        self::assertCount(1, $cookies);
        self::assertSame('token', $cookies[0]->getValue());
        // Once.
        self::assertSame([], $this->getResponse(new ApiResponseDTO(['a' => 1]), $request)->getCookies());
    }

    #[DataProvider('corsProvider')]
    public function testCorsHeaders(string $allowedOrigins, ?string $origin, ?string $expectedAllowOrigin): void {
        $request = TestRequest::create(headers: ($origin !== null) ? ['origin' => $origin] : []);
        $headers = $this->getResponse(new ApiResponseDTO(['ok' => true]), $request, $allowedOrigins)->getHeaders();

        self::assertSame($expectedAllowOrigin, $headers['Access-Control-Allow-Origin'] ?? null);

        if ($expectedAllowOrigin !== null) {
            self::assertSame('Origin', $headers['Vary']);
            self::assertSame('GET, POST', $headers['Access-Control-Allow-Methods']);
            self::assertSame('Content-Type, X-Token', $headers['Access-Control-Allow-Headers']);
        } else {
            self::assertArrayNotHasKey('Access-Control-Allow-Methods', $headers);
            self::assertArrayNotHasKey('Access-Control-Allow-Headers', $headers);
            self::assertArrayNotHasKey('Vary', $headers);
        }
    }

    /**
     * @return array<string, array{string, string|null, string|null}>
     */
    public static function corsProvider(): array {
        return [
            'disabled'          => ['', 'https://evil.example', null],
            'same-site request' => ['https://app.example', null, null],
            'allowed origin'    => ['https://app.example, https://admin.example', 'https://admin.example', 'https://admin.example'],
            'trailing slash'    => ['https://app.example/', 'https://app.example', 'https://app.example'],
            'other origin'      => ['https://app.example', 'https://evil.example', null],
            'any origin'        => ['*', 'https://any.example', '*'],
        ];
    }

    public function testCorsOfInvalidMethodsAndHeaders(): void {
        $request = TestRequest::create(headers: ['origin' => 'https://app.example']);
        $response = new ApiResponseController(
            new EnvStub(['API_ALLOW_METHODS' => ['GET'], 'API_ALLOW_HEADERS' => 5]),
            new Response(),
            $this->logger,
            $request,
            new SessionCookie($request),
            new AllowedOriginsProvider(new EnvStub(['API_ALLOW_ORIGIN' => '*']))
        )->getResponse(new ApiResponseDTO(['a' => 1]));

        self::assertSame('', $response->getHeaders()['Access-Control-Allow-Methods']);
        // The parser types the values.
        self::assertSame('5', $response->getHeaders()['Access-Control-Allow-Headers']);
    }

    protected function getResponse(
        ApiResponseDTO $response,
        ?Request       $request = null,
        string         $allowedOrigins = ''
    ): Response {
        $request ??= TestRequest::create();

        return new ApiResponseController(
            new EnvStub(['API_ALLOW_METHODS' => 'GET, POST', 'API_ALLOW_HEADERS' => 'Content-Type, X-Token']),
            new Response(),
            $this->logger,
            $request,
            new SessionCookie($request),
            new AllowedOriginsProvider(new EnvStub(['API_ALLOW_ORIGIN' => $allowedOrigins]))
        )->getResponse($response);
    }
}
