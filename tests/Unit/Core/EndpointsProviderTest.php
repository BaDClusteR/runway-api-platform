<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Unit\Core;

use ApiPlatform\Attribute\Parameter;
use ApiPlatform\Core\Provider\Endpoints\EndpointsProvider;
use ApiPlatform\DTO\ApiEndpointDTO;
use ApiPlatform\Exception\EndpointDefinitionException;
use ApiPlatform\Tests\Fixture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Runway\Router\Compiler\RouteCompiler;

class EndpointsProviderTest extends TestCase {
    public function testPathsAreJoinedWithClassPrefix(): void {
        $endpoints = $this->getEndpoints();

        self::assertSame('chats/{chatId}', $endpoints['get']->path);
        self::assertSame('chats/{chatId}/chunks/{index}', $endpoints['chunk']->path);
        self::assertSame('GET', $endpoints['chunk']->requestMethod);
        self::assertSame('PATCH', $endpoints['updateMeta']->requestMethod);
        self::assertSame(Fixture\ChatEndpoints::class, $endpoints['chunk']->class);
        self::assertSame('chunk', $endpoints['chunk']->method);
        self::assertTrue($endpoints['chunk']->isPublic);
        self::assertFalse($endpoints['private']->isPublic);
    }

    public function testSlashesOfPathsAndMethodCase(): void {
        $endpoints = $this->getEndpoints(Fixture\MiscEndpoints::class);

        self::assertSame('misc/points/{x}/{y}/{visible}', $endpoints['point']->path);
        self::assertSame('POST', $endpoints['point']->requestMethod);
    }

    public function testOnlyPublicMethodsWithAttributes(): void {
        self::assertSame(
            ['point', 'item', 'mixed', 'validated', 'body', 'upload'],
            array_keys($this->getEndpoints(Fixture\MiscEndpoints::class))
        );
    }

    public function testPlaceholderArgumentsAreTakenFromPath(): void {
        $parameters = $this->getEndpoints()['updateMeta']->parameters;

        self::assertSame(['chatId', 'messageId', 'meta'], array_keys($parameters));
        self::assertSame(Parameter::SOURCE_PATH, $parameters['chatId']->source);
        self::assertSame('chatId', $parameters['chatId']->name);
        self::assertSame('chatId', $parameters['chatId']->argumentName);
        self::assertFalse($parameters['chatId']->hasDefaultValue);
        self::assertSame(Parameter::SOURCE_PATH, $parameters['messageId']->source);
        self::assertSame(Parameter::SOURCE_BODY, $parameters['meta']->source);
    }

    public function testDefaultValues(): void {
        $parameters = $this->getEndpoints()['messages']->parameters;

        self::assertSame(Parameter::SOURCE_QUERY, $parameters['direction']->source);
        self::assertTrue($parameters['direction']->hasDefaultValue);
        self::assertSame('before', $parameters['direction']->defaultValue);
        self::assertTrue($parameters['number']->hasDefaultValue);
        self::assertNull($parameters['number']->defaultValue);
    }

    public function testArgumentWithDefaultValueAndWithoutSource(): void {
        $parameter = $this->getEndpoints()['messages']->parameters['isInternal'];

        self::assertSame('', $parameter->source);
        self::assertTrue($parameter->hasDefaultValue);
        self::assertFalse($parameter->defaultValue);
    }

    public function testRequirementsByArgumentType(): void {
        $endpoints = $this->getEndpoints();
        $misc = $this->getEndpoints(Fixture\MiscEndpoints::class);

        self::assertSame(['chatId' => '\d+', 'index' => '\d+'], $endpoints['chunk']->requirements);
        // A string has the default requirement of the router.
        self::assertSame(['chatId' => '\d+'], $endpoints['file']->requirements);
        self::assertSame(
            ['x' => '\d+(?:\.\d+)?', 'y' => '\d+(?:\.\d+)?', 'visible' => '0|1|true|false'],
            $misc['point']->requirements
        );
        // Not a named type.
        self::assertSame([], $misc['mixed']->requirements);
        // The body and the query are not in the path.
        self::assertSame([], $misc['validated']->requirements);
    }

    public function testPathArgumentWithAnotherName(): void {
        $parameter = $this->getEndpoints()['file']->parameters['filename'];

        self::assertSame(Parameter::SOURCE_PATH, $parameter->source);
        self::assertSame('name', $parameter->name);
        self::assertSame('filename', $parameter->argumentName);
    }

    public function testSeveralEndpointsOfMethod(): void {
        $endpoints = array_values(array_filter(
            $this->provider(Fixture\ChatEndpoints::class)->getApiEndpoints(),
            static fn(ApiEndpointDTO $endpoint): bool => $endpoint->method === 'file'
        ));

        self::assertSame(
            ['chats/{chatId}/files/{name}', 'chats/{chatId}/attachments/{name}'],
            array_map(static fn(ApiEndpointDTO $endpoint): string => $endpoint->path, $endpoints)
        );
        self::assertSame(
            ['api.' . Fixture\ChatEndpoints::class . '::file', 'api.' . Fixture\ChatEndpoints::class . '::file.1'],
            array_map(static fn(ApiEndpointDTO $endpoint): string => $endpoint->routeName, $endpoints)
        );
    }

    public function testEndpointByRouteName(): void {
        $provider = $this->provider(Fixture\ChatEndpoints::class);
        $endpoint = $provider->getApiEndpoints()[1];

        self::assertSame($endpoint, $provider->getEndpointByRouteName($endpoint->routeName));
        self::assertSame($endpoint, $provider->getEndpointByRouteName('api.' . Fixture\ChatEndpoints::class . '::chunk'));
        self::assertNull($provider->getEndpointByRouteName('unknown'));
    }

    /**
     * The endpoints are collected once.
     */
    public function testEndpointsAreCached(): void {
        $provider = $this->provider(Fixture\ChatEndpoints::class);

        self::assertSame($provider->getApiEndpoints(), $provider->getApiEndpoints());
    }

    public function testServicesAndClassNames(): void {
        $endpoints = new EndpointsProvider(
            new RouteCompiler(),
            [new Fixture\ContactFolderEndpoints(), Fixture\DocumentFolderEndpoints::class]
        )->getApiEndpoints();

        self::assertCount(2, $endpoints);
    }

    public function testInheritedEndpointsBelongToSubclass(): void {
        $endpoints = new EndpointsProvider(
            new RouteCompiler(),
            [Fixture\ContactFolderEndpoints::class, Fixture\DocumentFolderEndpoints::class]
        )->getApiEndpoints();

        self::assertSame(
            [
                ['contact-folders/{folderId}/name', Fixture\ContactFolderEndpoints::class],
                ['document-folders/{folderId}/name', Fixture\DocumentFolderEndpoints::class],
            ],
            array_map(static fn(ApiEndpointDTO $endpoint): array => [$endpoint->path, $endpoint->class], $endpoints)
        );
        self::assertNotSame($endpoints[0]->routeName, $endpoints[1]->routeName);
    }

    #[DataProvider('invalidEndpointProvider')]
    public function testInvalidEndpoint(string $class, string $message): void {
        $this->expectException(EndpointDefinitionException::class);
        $this->expectExceptionMessageMatches($message);

        $this->provider($class)->getApiEndpoints();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidEndpointProvider(): array {
        $prefix = preg_quote('ApiPlatform\Tests\Fixture\Invalid\\', '/');

        return [
            'placeholder without argument' => [
                Fixture\Invalid\PlaceholderWithoutArgument::class,
                "/^{$prefix}PlaceholderWithoutArgument::run\(\): path \"chats\/\{chatId}\/messages\/\{messageId}\" has"
                . ' placeholder \{messageId}, but the method has no argument for it\.$/',
            ],
            'path argument without placeholder' => [
                Fixture\Invalid\PathArgumentWithoutPlaceholder::class,
                "/^{$prefix}PathArgumentWithoutPlaceholder::run\(\): argument \\\$messageId is taken from the path"
                . ' placeholder \{messageId}, but path "chats\/\{chatId}" has no such placeholder\.$/',
            ],
            'argument without source' => [
                Fixture\Invalid\ArgumentWithoutSource::class,
                "/^{$prefix}ArgumentWithoutSource::run\(\): argument \\\$limit has no source: it's not a path"
                . ' placeholder, and it has neither a Parameter attribute nor a default value\.$/',
            ],
            'invalid path' => [Fixture\Invalid\InvalidPath::class, "/^{$prefix}InvalidPath::run\(\): .*is not closed/"],
            'invalid source' => [
                Fixture\Invalid\InvalidSource::class,
                "/^{$prefix}InvalidSource::run\(\): argument \\\$session has invalid source \"cookie\", allowed"
                . ' sources are path, query, body, file\.$/',
            ],
            'unknown class' => ['App\Unknown', '/^Class "App\\\\Unknown" of the endpoints does not exist\.$/'],
        ];
    }

    /**
     * @return array<string, ApiEndpointDTO> Method name => the first endpoint of the method.
     */
    protected function getEndpoints(string $class = Fixture\ChatEndpoints::class): array {
        $endpoints = [];

        foreach ($this->provider($class)->getApiEndpoints() as $endpoint) {
            $endpoints[$endpoint->method] ??= $endpoint;
        }

        return $endpoints;
    }

    protected function provider(string $class): EndpointsProvider {
        return new EndpointsProvider(new RouteCompiler(), [$class]);
    }
}
