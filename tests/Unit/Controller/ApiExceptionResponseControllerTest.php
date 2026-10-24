<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Unit\Controller;

use ApiPlatform\Controller\System\ApiExceptionResponseController;
use ApiPlatform\Controller\System\ApiResponseController;
use ApiPlatform\Core\Provider\AllowedOrigins\AllowedOriginsProvider;
use ApiPlatform\Core\Storage\SessionCookie;
use ApiPlatform\Exception\ApiException;
use ApiPlatform\Exception\BadRequestException;
use ApiPlatform\Exception\InternalErrorException;
use ApiPlatform\Exception\RuntimeInternalErrorException;
use ApiPlatform\Tests\Support\EnvStub;
use ApiPlatform\Tests\Support\LoggerSpy;
use ApiPlatform\Tests\Support\TestRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Runway\Request\Response;
use Runway\Singleton\IKernel;
use Throwable;

class ApiExceptionResponseControllerTest extends TestCase {
    protected LoggerSpy $logger;

    protected function setUp(): void {
        $this->logger = new LoggerSpy();
    }

    public function testClientError(): void {
        $response = $this->getResponse(new BadRequestException(['number is not a number.', 'text is empty.']));

        self::assertSame(400, $response->getCode());
        self::assertSame(
            [
                'errors'    => [
                    ['code' => 400, 'message' => 'number is not a number.'],
                    ['code' => 400, 'message' => 'text is empty.'],
                ],
                'requestId' => 'request-id',
            ],
            $this->getBody($response)
        );
    }

    /**
     * Expected: the info level, without the trace.
     */
    public function testClientErrorIsLogged(): void {
        $this->getResponse(new BadRequestException('number is not a number.'));

        self::assertSame(
            [
                'level'   => 'info',
                'message' => 'API error: number is not a number.',
                'context' => ['code' => 400, 'requestId' => 'request-id'],
            ],
            $this->logger->records[0]
        );
        self::assertSame([], $this->logger->getRecords('error'));
    }

    /**
     * The messages of the internal errors can tell about the code and the data.
     */
    #[DataProvider('internalErrorProvider')]
    public function testInternalErrorIsHidden(Throwable $exception): void {
        $response = $this->getResponse($exception);

        self::assertSame(500, $response->getCode());
        self::assertSame(
            ['errors' => [['code' => 500, 'message' => 'Internal server error']], 'requestId' => 'request-id'],
            $this->getBody($response)
        );
    }

    /**
     * @return array<string, array{Throwable}>
     */
    public static function internalErrorProvider(): array {
        return [
            'API internal error' => [new InternalErrorException("Argument 'x' of App\\Secret::run() is not described.")],
            'runtime error'      => [new RuntimeInternalErrorException('SQL: SELECT * FROM users')],
            'PHP error'          => [new RuntimeException('/var/www/config.php')],
            // An invalid code of an API exception is an internal error.
            'no code'            => [new ApiException(['Something'])],
            'success code'       => [new ApiException(['Something'], 200)],
            'too big code'       => [new ApiException(['Something'], 600)],
        ];
    }

    public function testOtherServerErrorCode(): void {
        $response = $this->getResponse(new ApiException(['Maintenance'], 503));

        self::assertSame(503, $response->getCode());
        self::assertSame([['code' => 503, 'message' => 'Internal server error']], $this->getBody($response)['errors']);
    }

    public function testClientErrorCodes(): void {
        self::assertSame(400, $this->getResponse(new ApiException(['a'], 400))->getCode());
        self::assertSame(499, $this->getResponse(new ApiException(['a'], 499))->getCode());
        self::assertSame(599, $this->getResponse(new ApiException(['a'], 599))->getCode());
        self::assertSame(500, $this->getResponse(new ApiException(['a'], 399))->getCode());
    }

    public function testInternalErrorInDebugMode(): void {
        $previous = new RuntimeException('The cause');
        $exception = new InternalErrorException('The details', $previous);

        $body = $this->getBody($this->getResponse($exception, isDebugMode: true));

        self::assertSame([['code' => 500, 'message' => 'The details']], $body['errors']);
        self::assertSame(__FILE__, $body['file']);
        self::assertSame($exception->getLine(), $body['line']);
        self::assertSame(explode("\n", $exception->getTraceAsString()), $body['trace']);
        self::assertArrayNotHasKey('exceptionType', $body);
        self::assertIsArray($body['previous']);
        self::assertSame([['code' => 500, 'message' => 'The cause']], $body['previous']['errors']);
        self::assertSame($previous->getLine(), $body['previous']['line']);
    }

    public function testPhpErrorInDebugMode(): void {
        $body = $this->getBody($this->getResponse(new RuntimeException('Division by zero'), isDebugMode: true));

        self::assertSame([['code' => 500, 'message' => 'Division by zero']], $body['errors']);
    }

    /**
     * The client errors need no trace even in the debug mode.
     */
    public function testClientErrorInDebugMode(): void {
        $body = $this->getBody($this->getResponse(new BadRequestException('a', new RuntimeException('b')), isDebugMode: true));

        self::assertSame(['errors', 'requestId'], array_keys($body));
    }

    /**
     * The log has the details of the internal errors anyway.
     */
    public function testInternalErrorIsLogged(): void {
        $previous = new RuntimeException('The cause');
        $exception = new InternalErrorException('The details', $previous);

        $this->getResponse($exception);

        $records = $this->logger->getRecords('error');
        self::assertCount(1, $records);
        self::assertSame('Uncaught API exception: The details', $records[0]['message']);

        $context = $records[0]['context'];
        self::assertSame([['code' => 500, 'message' => 'The details']], $context['errors']);
        self::assertSame('request-id', $context['requestId']);
        self::assertSame(InternalErrorException::class, $context['exceptionType']);
        self::assertSame(__FILE__, $context['file']);
        self::assertSame($exception->getLine(), $context['line']);
        // Without the arguments of the calls.
        self::assertSame(explode("\n", $exception->getTraceAsString()), $context['exceptionTrace']);
        self::assertArrayNotHasKey('trace', $context);
        self::assertIsArray($context['previous']);
        self::assertSame(RuntimeException::class, $context['previous']['exceptionType']);
        self::assertSame([['code' => 500, 'message' => 'The cause']], $context['previous']['errors']);
    }

    protected function getResponse(Throwable $exception, bool $isDebugMode = false): Response {
        $request = TestRequest::create();
        $kernel = $this->createStub(IKernel::class);
        $kernel->method('isDebugMode')->willReturn($isDebugMode);

        return new ApiExceptionResponseController(
            new ApiResponseController(
                new EnvStub(),
                new Response(),
                new LoggerSpy(),
                $request,
                new SessionCookie($request),
                new AllowedOriginsProvider(new EnvStub())
            ),
            $this->logger,
            $request,
            $kernel
        )->getResponse($exception);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getBody(Response $response): array {
        $body = json_decode($response->getBody(), true);
        self::assertIsArray($body);

        /** @var array<string, mixed> */
        return $body;
    }
}
