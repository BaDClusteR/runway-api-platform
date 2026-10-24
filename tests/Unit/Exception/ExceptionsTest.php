<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Unit\Exception;

use ApiPlatform\Exception\ApiException;
use ApiPlatform\Exception\ApiRuntimeException;
use ApiPlatform\Exception\AuthFailedException;
use ApiPlatform\Exception\AuthorizationRequiredException;
use ApiPlatform\Exception\BadRequestException;
use ApiPlatform\Exception\EndpointDefinitionException;
use ApiPlatform\Exception\EndpointNotFoundException;
use ApiPlatform\Exception\InternalErrorException;
use ApiPlatform\Exception\MethodNotAllowedException;
use ApiPlatform\Exception\NotFoundException;
use ApiPlatform\Exception\RequiredParameterNotProvidedException;
use ApiPlatform\Exception\RuntimeInternalErrorException;
use ApiPlatform\Exception\TooManyRequestsException;
use ApiPlatform\Exception\UnauthorizedException;
use ApiPlatform\Exception\UnprocessableContentException;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ExceptionsTest extends TestCase {
    /**
     * The exceptions are made by the test: the coverage of the constructors.
     *
     * @param Closure(): (ApiException|ApiRuntimeException) $factory
     * @param string[]                                        $errors
     */
    #[DataProvider('exceptionProvider')]
    public function testException(Closure $factory, int $code, array $errors): void {
        $exception = $factory();

        self::assertSame($code, $exception->getCode());
        self::assertSame($errors, $exception->errors);
        // The message of the logs.
        self::assertSame(implode("\n", $errors), $exception->getMessage());
    }

    /**
     * @return array<string, array{Closure(): (ApiException|ApiRuntimeException), int, string[]}>
     */
    public static function exceptionProvider(): array {
        return [
            'API'                      => [static fn() => new ApiException(['a', 'b'], 409), 409, ['a', 'b']],
            'API without code'         => [static fn() => new ApiException(['a']), 0, ['a']],
            'runtime'                  => [static fn() => new ApiRuntimeException(['a', 'b'], 503), 503, ['a', 'b']],
            'runtime without code'     => [static fn() => new ApiRuntimeException(['a']), 0, ['a']],
            'bad request'              => [static fn() => new BadRequestException(['a', 'b']), 400, ['a', 'b']],
            'bad request of one error' => [static fn() => new BadRequestException('a'), 400, ['a']],
            'not found'                => [static fn() => new NotFoundException('No chat 42.'), 404, ['No chat 42.']],
            'not found of errors'      => [static fn() => new NotFoundException(['a', 'b']), 404, ['a', 'b']],
            'too many requests'        => [static fn() => new TooManyRequestsException('Wait.'), 429, ['Wait.']],
            'too many of errors'       => [static fn() => new TooManyRequestsException(['a', 'b']), 429, ['a', 'b']],
            'unauthorized'             => [static fn() => new UnauthorizedException(), 401, ['API token is invalid or not provided.']],
            'unauthorized of message'  => [static fn() => new UnauthorizedException('Expired.'), 401, ['Expired.']],
            'auth failed'              => [static fn() => new AuthFailedException(), 401, ['Invalid credentials.']],
            'authorization required'   => [static fn() => new AuthorizationRequiredException(), 401, ['Not authorized']],
            'unprocessable'            => [static fn() => new UnprocessableContentException('Not JSON'), 422, ['Not JSON']],
            'internal error'           => [static fn() => new InternalErrorException('Oops'), 500, ['Oops']],
            'definition'               => [static fn() => new EndpointDefinitionException('No argument'), 500, ['No argument']],
            'runtime internal error'   => [static fn() => new RuntimeInternalErrorException('Oops'), 500, ['Oops']],
            'required parameter'       => [
                static fn() => new RequiredParameterNotProvidedException('number'),
                400,
                ['Required parameter number is not provided.'],
            ],
            'endpoint not found'       => [
                static fn() => new EndpointNotFoundException('/api/chats', 'GET'),
                404,
                ['[GET] /api/chats is not valid API endpoint.'],
            ],
            'method not allowed'       => [
                static fn() => new MethodNotAllowedException('/api/chats', 'DELETE', ['GET', 'HEAD']),
                405,
                ['[DELETE] /api/chats: the method is not allowed, allowed methods: GET, HEAD.'],
            ],
        ];
    }

    /**
     * @param Closure(): (ApiException|ApiRuntimeException) $factory
     */
    #[DataProvider('previousProvider')]
    public function testPrevious(Closure $factory): void {
        self::assertSame('The cause', $factory()->getPrevious()?->getMessage());
    }

    /**
     * @return array<string, array{Closure(): (ApiException|ApiRuntimeException)}>
     */
    public static function previousProvider(): array {
        $previous = new RuntimeException('The cause');

        return [
            'API'                    => [static fn() => new ApiException(['a'], 400, $previous)],
            'runtime'                => [static fn() => new ApiRuntimeException(['a'], 500, $previous)],
            'bad request'            => [static fn() => new BadRequestException('a', $previous)],
            'not found'              => [static fn() => new NotFoundException('a', $previous)],
            'too many requests'      => [static fn() => new TooManyRequestsException('a', $previous)],
            'unauthorized'           => [static fn() => new UnauthorizedException('a', $previous)],
            'auth failed'            => [static fn() => new AuthFailedException($previous)],
            'authorization required' => [static fn() => new AuthorizationRequiredException($previous)],
            'unprocessable'          => [static fn() => new UnprocessableContentException('a', $previous)],
            'internal error'         => [static fn() => new InternalErrorException('a', $previous)],
            'runtime internal error' => [static fn() => new RuntimeInternalErrorException('a', $previous)],
            'required parameter'     => [static fn() => new RequiredParameterNotProvidedException('a', $previous)],
            'endpoint not found'     => [static fn() => new EndpointNotFoundException('/a', 'GET', $previous)],
            'method not allowed'     => [static fn() => new MethodNotAllowedException('/a', 'GET', [], $previous)],
        ];
    }

    public function testEndpointNotFoundDetails(): void {
        $exception = new EndpointNotFoundException('/api/chats', 'GET');

        self::assertSame('/api/chats', $exception->getEndpoint());
        self::assertSame('GET', $exception->getMethod());
    }

    public function testMethodNotAllowedDetails(): void {
        $exception = new MethodNotAllowedException('/api/chats', 'DELETE', ['GET']);

        self::assertSame('/api/chats', $exception->endpoint);
        self::assertSame('DELETE', $exception->method);
        self::assertSame(['GET'], $exception->allowedMethods);
    }
}
