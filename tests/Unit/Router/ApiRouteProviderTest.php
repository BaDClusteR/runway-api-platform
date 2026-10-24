<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Unit\Router;

use ApiPlatform\Controller\IApiController;
use ApiPlatform\Core\Provider\Endpoints\EndpointsProvider;
use ApiPlatform\Router\ApiRouteProvider;
use ApiPlatform\Tests\Fixture\ChatEndpoints;
use ApiPlatform\Tests\Fixture\MiscEndpoints;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Runway\Router\Compiler\RouteCompiler;
use Runway\Service\DTO\RouteDTO;

class ApiRouteProviderTest extends TestCase {
    public function testRoutes(): void {
        $routes = $this->getRoutes('/api');
        $route = $routes['api.' . ChatEndpoints::class . '::chunk'];

        self::assertSame('/api/chats/{chatId}/chunks/{index}', $route->path);
        self::assertSame(IApiController::class, $route->controller);
        self::assertSame('handle', $route->method);
        self::assertSame(0, $route->priority);
        self::assertSame(ChatEndpoints::class . '::chunk()', $route->description);
        self::assertSame(ChatEndpoints::class, $route->filePath);
        self::assertSame(['GET'], $route->methods);
        self::assertSame(['chatId' => '\d+', 'index' => '\d+'], $route->requirements);
    }

    public function testRouteOfEveryEndpoint(): void {
        self::assertCount(
            count(new EndpointsProvider(new RouteCompiler(), [ChatEndpoints::class, MiscEndpoints::class])->getApiEndpoints()),
            $this->getRoutes('/api')
        );
        self::assertSame(['POST'], $this->getRoutes('/api')['api.' . MiscEndpoints::class . '::point']->methods);
    }

    #[DataProvider('prefixProvider')]
    public function testPrefix(?string $prefix, string $path): void {
        self::assertSame($path, $this->getRoutes($prefix)['api.' . ChatEndpoints::class . '::get']->path);
    }

    /**
     * @return array<string, array{string|null, string}>
     */
    public static function prefixProvider(): array {
        return [
            'default'         => [null, '/api/chats/{chatId}'],
            'trailing slash'  => ['/v2/', '/v2/chats/{chatId}'],
            'nested'          => ['/backend/api', '/backend/api/chats/{chatId}'],
            // The site root.
            'no prefix'       => ['', '/chats/{chatId}'],
        ];
    }

    /**
     * @return array<string, RouteDTO> By name.
     */
    protected function getRoutes(?string $prefix): array {
        $endpointsProvider = new EndpointsProvider(new RouteCompiler(), [ChatEndpoints::class, MiscEndpoints::class]);
        $provider = ($prefix !== null)
            ? new ApiRouteProvider($endpointsProvider, $prefix)
            : new ApiRouteProvider($endpointsProvider);

        $routes = [];

        foreach ($provider->getRoutes() as $route) {
            $routes[$route->name] = $route;
        }

        return $routes;
    }
}
