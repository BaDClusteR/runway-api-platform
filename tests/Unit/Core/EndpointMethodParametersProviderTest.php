<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Unit\Core;

use ApiPlatform\Attribute\Assert\Length;
use ApiPlatform\Attribute\Assert\NotEmpty;
use ApiPlatform\Attribute\Assert\Range;
use ApiPlatform\Core\Provider\EndpointMethodParameters\EndpointMethodParametersProvider;
use ApiPlatform\Core\Provider\EndpointMethodParameterValue\EndpointMethodParameterValueProvider;
use ApiPlatform\Core\Provider\Endpoints\EndpointsProvider;
use ApiPlatform\DTO\ApiEndpointArgumentFileDTO;
use ApiPlatform\DTO\ApiEndpointDTO;
use ApiPlatform\DTO\ApiEndpointMethodParameterDTO;
use ApiPlatform\DTO\ApiRequestDTO;
use ApiPlatform\Exception\InternalErrorException;
use ApiPlatform\Tests\Fixture\ChatEndpoints;
use ApiPlatform\Tests\Fixture\MiscEndpoints;
use ApiPlatform\Tests\Support\TestRequest;
use PHPUnit\Framework\TestCase;
use Runway\Router\Compiler\RouteCompiler;

class EndpointMethodParametersProviderTest extends TestCase {
    public function testParameters(): void {
        $parameters = $this->getParameters(
            MiscEndpoints::class,
            'validated',
            new ApiRequestDTO('POST', [], ['code' => 'abc'], [])
        );

        self::assertSame(['code', 'count'], array_keys($parameters));

        self::assertSame('code', $parameters['code']->name);
        self::assertSame('code', $parameters['code']->argumentName);
        self::assertSame('string', $parameters['code']->type);
        self::assertFalse($parameters['code']->isNullable);
        self::assertSame('abc', $parameters['code']->value);
        self::assertEquals([new Length(maxLength: 3), new NotEmpty()], $parameters['code']->assertions);

        self::assertSame('int', $parameters['count']->type);
        self::assertTrue($parameters['count']->isNullable);
        // The default value: there is no query parameter.
        self::assertNull($parameters['count']->value);
        self::assertEquals([new Range(min: 1, max: 10)], $parameters['count']->assertions);
    }

    public function testRenamedArgument(): void {
        $parameters = $this->getParameters(
            ChatEndpoints::class,
            'file',
            new ApiRequestDTO('GET', ['chatId' => '42', 'name' => 'report.pdf'], [], [])
        );

        self::assertSame('name', $parameters['filename']->name);
        self::assertSame('filename', $parameters['filename']->argumentName);
        self::assertSame('report.pdf', $parameters['filename']->value);
    }

    public function testFileType(): void {
        $parameters = $this->getParameters(MiscEndpoints::class, 'upload');

        self::assertSame(ApiEndpointArgumentFileDTO::class, $parameters['file']->type);
        self::assertTrue($parameters['preview']->isNullable);
    }

    /**
     * Not supported by the validator, but described.
     */
    public function testUnionType(): void {
        self::assertSame('string|int', $this->getParameters(MiscEndpoints::class, 'mixed')['value']->type);
    }

    /**
     * For the docs: the arguments without the values.
     */
    public function testWithoutRequest(): void {
        $parameters = $this->getParameters(ChatEndpoints::class, 'get');

        self::assertNull($parameters['chatId']->value);
    }

    public function testArgumentIsNotDescribed(): void {
        $this->expectException(InternalErrorException::class);
        $this->expectExceptionMessage("Argument 'chatId' of " . ChatEndpoints::class . '::get() is not described.');

        $this->provider()->getEndpointMethodParameters(new ApiEndpointDTO('chats', 'GET', ChatEndpoints::class, 'get', true));
    }

    public function testUnknownMethod(): void {
        $this->expectException(InternalErrorException::class);
        $this->expectExceptionCode(500);

        $this->provider()->getEndpointMethodParameters(new ApiEndpointDTO('chats', 'GET', ChatEndpoints::class, 'unknown', true));
    }

    /**
     * @param class-string $class
     *
     * @return array<string, ApiEndpointMethodParameterDTO> By argument name.
     */
    protected function getParameters(string $class, string $method, ?ApiRequestDTO $request = null): array {
        $endpoint = array_find(
            new EndpointsProvider(new RouteCompiler(), [$class])->getApiEndpoints(),
            static fn(ApiEndpointDTO $endpoint): bool => $endpoint->method === $method
        );
        self::assertNotNull($endpoint);

        $parameters = [];

        foreach ($this->provider()->getEndpointMethodParameters($endpoint, $request) as $parameter) {
            $parameters[$parameter->argumentName] = $parameter;
        }

        return $parameters;
    }

    protected function provider(): EndpointMethodParametersProvider {
        return new EndpointMethodParametersProvider(new EndpointMethodParameterValueProvider(TestRequest::create()));
    }
}
