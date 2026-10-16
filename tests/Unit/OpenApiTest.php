<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ApiPlatform\OpenAPI\IOpenApiGenerator;
use Runway\Singleton\Container;

/**
 * The spec of the fixture endpoints (see tests/Fixture).
 */
class OpenApiTest extends TestCase {
    public function testPathsAndParameters(): void {
        $spec = $this->getSpec();

        self::assertArrayHasKey('/api/chats/{chatId}/chunks/{index}', $spec['paths']);
        // Without the inline requirement.
        self::assertArrayHasKey('/api/chats/{chatId}/years/{year}', $spec['paths']);

        $parameters = $spec['paths']['/api/chats/{chatId}/messages']['get']['parameters'];

        self::assertSame(['chatId', 'number', 'direction'], array_column($parameters, 'name'));
        self::assertSame(['path', 'query', 'query'], array_column($parameters, 'in'));
        self::assertTrue($parameters[0]['required']);
        self::assertSame('integer', $parameters[0]['schema']['type']);
    }

    public function testResponseSchema(): void {
        $schema = $this->getSpec()['components']['schemas']['ApiPlatform.Tests.Fixture.ResultDTO'] ?? null;

        self::assertSame('object', $schema['type'] ?? null);
        self::assertSame(['type' => 'array', 'items' => ['type' => 'string']], $schema['properties']['result']);
    }

    public function testFreeFormObjects(): void {
        $schema = $this->getSpec()['components']['schemas']['ApiPlatform.Tests.Fixture.TreeDTO'] ?? null;

        self::assertSame(['type' => 'array', 'items' => ['type' => 'object']], $schema['properties']['tree']);
        self::assertSame(['nullable' => true, 'allOf' => [['type' => 'object']]], $schema['properties']['meta']);
    }

    protected function getSpec(): array {
        return json_decode(
            json_encode(Container::getInstance()->getService(IOpenApiGenerator::class)->generateOpenApiSpec()),
            true
        );
    }
}
