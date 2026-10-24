<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Integration;

use ApiPlatform\OpenAPI\IOpenApiGenerator;
use ApiPlatform\Tests\Support\ArrayPath;
use PHPUnit\Framework\TestCase;
use Runway\Singleton\Container;

/**
 * The spec of the platform and the fixture endpoints (see tests/Fixture) by the services of the container.
 */
class OpenApiTest extends TestCase {
    use ArrayPath;

    public function testPathsAndParameters(): void {
        $spec = $this->getSpec();

        self::assertArrayHasKey('/api/chats/{chatId}/chunks/{index}', self::arrayAt($spec, 'paths'));
        // Without the inline requirement.
        self::assertArrayHasKey('/api/chats/{chatId}/years/{year}', self::arrayAt($spec, 'paths'));
        self::assertArrayHasKey('/api/misc/items/{id}', self::arrayAt($spec, 'paths'));

        $parameters = self::arrayAt($spec, 'paths', '/api/chats/{chatId}/messages', 'get', 'parameters');

        self::assertSame(['chatId', 'number', 'direction'], array_column($parameters, 'name'));
        self::assertSame(['path', 'query', 'query'], array_column($parameters, 'in'));
        self::assertTrue(self::at($parameters, 0, 'required'));
        self::assertSame('integer', self::at($parameters, 0, 'schema', 'type'));
    }

    /**
     * The endpoints of the platform: the authorization.
     */
    public function testAuthEndpoints(): void {
        $paths = self::arrayAt($this->getSpec(), 'paths');

        self::assertSame(['Authorization'], self::at($paths, '/api/auth/login', 'post', 'tags'));
        self::assertSame(
            ['login', 'password'],
            self::at($paths, '/api/auth/login', 'post', 'requestBody', 'content', 'application/json', 'schema', 'required')
        );
        self::assertSame(
            '#/components/schemas/Auth.LoginDTO',
            self::at($paths, '/api/auth/login', 'post', 'responses', 200, 'content', 'application/json', 'schema', '$ref')
        );
        self::assertSame('Done', self::at($paths, '/api/auth/logout', 'post', 'responses', 204, 'description'));
        // The private endpoints need the token.
        self::assertSame([['Token' => []]], self::at($paths, '/api/chats/{chatId}/private', 'get', 'security'));
    }

    public function testResponseSchema(): void {
        $schema = self::arrayAt($this->getSpec(), 'components', 'schemas', 'ApiPlatform.Tests.Fixture.ResultDTO');

        self::assertSame('object', self::at($schema, 'type'));
        self::assertSame(['type' => 'array', 'items' => ['type' => 'string']], self::at($schema, 'properties', 'result'));
    }

    public function testFreeFormObjects(): void {
        $schema = self::arrayAt($this->getSpec(), 'components', 'schemas', 'ApiPlatform.Tests.Fixture.TreeDTO');

        self::assertSame(['type' => 'array', 'items' => ['type' => 'object']], self::at($schema, 'properties', 'tree'));
        self::assertSame(['nullable' => true, 'allOf' => [['type' => 'object']]], self::at($schema, 'properties', 'meta'));
    }

    /**
     * @return array<mixed> Decoded from JSON, as a client gets it.
     */
    protected function getSpec(): array {
        $generator = Container::getInstance()->getService(IOpenApiGenerator::class);
        self::assertInstanceOf(IOpenApiGenerator::class, $generator);

        $spec = json_decode((string)json_encode($generator->generateOpenApiSpec()), true);
        self::assertIsArray($spec);

        return $spec;
    }
}
