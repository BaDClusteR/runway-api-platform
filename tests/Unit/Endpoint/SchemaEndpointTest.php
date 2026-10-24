<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Unit\Endpoint;

use ApiPlatform\Endpoint\DTO\PingDTO;
use ApiPlatform\Endpoint\Ping;
use ApiPlatform\Endpoint\Schema;
use ApiPlatform\IApi;
use ApiPlatform\OpenAPI\IOpenApiGenerator;
use PHPUnit\Framework\TestCase;
use Runway\Request\IResponse;
use Runway\Request\Response;
use Runway\Singleton\IKernel;

/**
 * The response of "GET schema" (the endpoint itself ends the process: see the E2E tests).
 */
class SchemaEndpointTest extends TestCase {
    public function testSchema(): void {
        $generator = $this->createStub(IOpenApiGenerator::class);
        $generator->method('generateOpenApiSpec')->willReturn(['openapi' => '3.0.0', 'paths' => ['/api/a/b' => []]]);

        $schema = new class (
            $this->createStub(IKernel::class),
            new Response(200, '', ['Content-Type' => 'application/json']),
            $generator,
            $this->createStub(IApi::class)
        ) extends Schema {
            public function response(): IResponse {
                return $this->getResponse();
            }
        };

        $response = $schema->response();

        self::assertSame("{\n    \"openapi\": \"3.0.0\",\n    \"paths\": {\n        \"\\/api\\/a\\/b\": []\n    }\n}", $response->getBody());
        self::assertSame(200, $response->getCode());
        self::assertSame('application/json', $response->getHeaders()['Content-Type']);
    }

    public function testPing(): void {
        self::assertEquals(new PingDTO(), new Ping()->ping());
    }
}
