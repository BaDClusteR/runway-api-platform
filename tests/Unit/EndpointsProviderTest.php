<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Unit;

use ApiPlatform\Attribute\Parameter;
use ApiPlatform\Core\Provider\Endpoints\EndpointsProvider;
use ApiPlatform\DTO\ApiEndpointDTO;
use ApiPlatform\Exception\EndpointDefinitionException;
use ApiPlatform\Tests\Fixture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Runway\Router\Compiler\RouteCompiler;

require_once dirname(__DIR__) . '/Fixture/InvalidEndpoints.php';

class EndpointsProviderTest extends TestCase {
    public function testPathsAreJoinedWithClassPrefix(): void {
        $endpoints = $this->getEndpoints();

        self::assertSame('chats/{chatId}', $endpoints['get']->path);
        self::assertSame('chats/{chatId}/chunks/{index}', $endpoints['chunk']->path);
        self::assertSame('GET', $endpoints['chunk']->requestMethod);
        self::assertSame('PATCH', $endpoints['updateMeta']->requestMethod);
        self::assertTrue($endpoints['chunk']->isPublic);
        self::assertFalse($endpoints['private']->isPublic);
    }

    public function testPlaceholderArgumentsAreTakenFromPath(): void {
        $parameters = $this->getEndpoints()['updateMeta']->parameters;

        self::assertSame(Parameter::SOURCE_PATH, $parameters['chatId']->source);
        self::assertSame(Parameter::SOURCE_PATH, $parameters['messageId']->source);
        self::assertSame(Parameter::SOURCE_BODY, $parameters['meta']->source);
    }

    public function testArgumentWithDefaultValueAndWithoutSource(): void {
        $parameter = $this->getEndpoints()['messages']->parameters['isInternal'];

        self::assertSame('', $parameter->source);
        self::assertFalse($parameter->defaultValue);
    }

    public function testRequirementsByArgumentType(): void {
        $endpoints = $this->getEndpoints();

        self::assertSame(['chatId' => '\d+', 'index' => '\d+'], $endpoints['chunk']->requirements);
        // A string has the default requirement of the router.
        self::assertSame(['chatId' => '\d+'], $endpoints['file']->requirements);
    }

    public function testPathArgumentWithAnotherName(): void {
        $parameter = $this->getEndpoints()['file']->parameters['filename'];

        self::assertSame(Parameter::SOURCE_PATH, $parameter->source);
        self::assertSame('name', $parameter->name);
    }

    public function testSeveralEndpointsOfMethod(): void {
        $paths = array_map(
            static fn(ApiEndpointDTO $endpoint): string => $endpoint->path,
            array_values(array_filter(
                $this->provider(Fixture\ChatEndpoints::class)->getApiEndpoints(),
                static fn(ApiEndpointDTO $endpoint): bool => $endpoint->method === 'file'
            ))
        );

        self::assertSame(['chats/{chatId}/files/{name}', 'chats/{chatId}/attachments/{name}'], $paths);
    }

    public function testEndpointByRouteName(): void {
        $provider = $this->provider(Fixture\ChatEndpoints::class);
        $endpoint = $provider->getApiEndpoints()[1];

        self::assertSame($endpoint, $provider->getEndpointByRouteName($endpoint->routeName));
        self::assertNull($provider->getEndpointByRouteName('unknown'));
    }

    #[DataProvider('invalidEndpointProvider')]
    public function testInvalidEndpoint(string $class, string $message): void {
        $this->expectException(EndpointDefinitionException::class);
        $this->expectExceptionMessageMatches($message);

        $this->provider($class)->getApiEndpoints();
    }

    public static function invalidEndpointProvider(): array {
        return [
            'placeholder without argument' => [
                Fixture\PlaceholderWithoutArgument::class,
                '/placeholder \{messageId}, but the method has no argument/',
            ],
            'path argument without placeholder' => [
                Fixture\PathArgumentWithoutPlaceholder::class,
                '/\$messageId is taken from the path placeholder \{messageId}/',
            ],
            'argument without source' => [Fixture\ArgumentWithoutSource::class, '/\$limit has no source/'],
            'invalid path' => [Fixture\InvalidPath::class, '/is not closed/'],
            'invalid source' => [Fixture\InvalidSource::class, '/invalid source "cookie"/'],
        ];
    }

    /**
     * @return array<string, ApiEndpointDTO> Method name => the first endpoint of the method.
     */
    protected function getEndpoints(): array {
        $endpoints = [];

        foreach ($this->provider(Fixture\ChatEndpoints::class)->getApiEndpoints() as $endpoint) {
            $endpoints[$endpoint->method] ??= $endpoint;
        }

        return $endpoints;
    }

    protected function provider(string $class): EndpointsProvider {
        return new EndpointsProvider(new RouteCompiler(), [$class]);
    }
}
