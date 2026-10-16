<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Unit;

use ApiPlatform\Controller\System\ApiResponseController;
use ApiPlatform\Core\Storage\SessionCookie;
use ApiPlatform\DTO\ApiResponseDTO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Runway\Env\Provider\IEnvVariablesProvider;
use Runway\Logger\ILogger;
use Runway\Request\IRequest;
use Runway\Request\Response;

class CorsTest extends TestCase {
    #[DataProvider('corsProvider')]
    public function testCorsHeaders(string $allowedOrigins, ?string $origin, ?string $expectedAllowOrigin): void {
        $env = $this->createStub(IEnvVariablesProvider::class);
        $env->method('getEnvVariable')->willReturnCallback(static fn(string $name): mixed => match ($name) {
            'API_ALLOW_ORIGIN'  => $allowedOrigins,
            'API_ALLOW_METHODS' => 'GET, POST',
            default             => '',
        });

        $request = $this->createStub(IRequest::class);
        $request->method('getHeader')->willReturn($origin);
        $request->method('getRequestId')->willReturn('id');

        $headers = new ApiResponseController($env, new Response(), $this->createStub(ILogger::class), $request, new SessionCookie($request))
            ->getResponse(new ApiResponseDTO(['ok' => true]))
            ->getHeaders();

        self::assertSame($expectedAllowOrigin, $headers['Access-Control-Allow-Origin'] ?? null);

        if ($expectedAllowOrigin !== null) {
            self::assertSame('Origin', $headers['Vary']);
            self::assertSame('GET, POST', $headers['Access-Control-Allow-Methods']);
        } else {
            self::assertArrayNotHasKey('Access-Control-Allow-Methods', $headers);
        }
    }

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
}
