<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Unit\Attribute;

use ApiPlatform\Attribute\Body;
use ApiPlatform\Attribute\Delete;
use ApiPlatform\Attribute\Endpoint;
use ApiPlatform\Attribute\File;
use ApiPlatform\Attribute\Get;
use ApiPlatform\Attribute\Parameter;
use ApiPlatform\Attribute\Patch;
use ApiPlatform\Attribute\Path;
use ApiPlatform\Attribute\Post;
use ApiPlatform\Attribute\Put;
use ApiPlatform\Attribute\Query;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EndpointAttributesTest extends TestCase {
    #[DataProvider('methodShortcutProvider')]
    public function testMethodShortcuts(Endpoint $attribute, string $method): void {
        self::assertSame($method, $attribute->method);
        self::assertSame('chats/{chatId}', $attribute->path);
        self::assertTrue($attribute->public);
    }

    /**
     * @return array<string, array{Endpoint, string}>
     */
    public static function methodShortcutProvider(): array {
        return [
            'GET'    => [new Get('chats/{chatId}', true), 'GET'],
            'POST'   => [new Post('chats/{chatId}', true), 'POST'],
            'PUT'    => [new Put('chats/{chatId}', true), 'PUT'],
            'PATCH'  => [new Patch('chats/{chatId}', true), 'PATCH'],
            'DELETE' => [new Delete('chats/{chatId}', true), 'DELETE'],
        ];
    }

    /**
     * An endpoint is private by default.
     *
     * @param class-string<Get|Post|Put|Patch|Delete> $class
     */
    #[DataProvider('defaultsProvider')]
    public function testShortcutDefaults(string $class): void {
        // Made by the test: the coverage of the constructor.
        $attribute = new $class();

        self::assertSame('', $attribute->path);
        self::assertFalse($attribute->public);
    }

    /**
     * @return array<string, array{class-string<Get|Post|Put|Patch|Delete>}>
     */
    public static function defaultsProvider(): array {
        return [
            'GET'    => [Get::class],
            'POST'   => [Post::class],
            'PUT'    => [Put::class],
            'PATCH'  => [Patch::class],
            'DELETE' => [Delete::class],
        ];
    }

    public function testEndpointIsPrivateByDefault(): void {
        self::assertFalse(new Endpoint('chats', 'GET')->public);
    }

    #[DataProvider('sourceShortcutProvider')]
    public function testSourceShortcuts(Parameter $attribute, string $source): void {
        self::assertSame($source, $attribute->source);
        self::assertSame('name', $attribute->name);
    }

    /**
     * @return array<string, array{Parameter, string}>
     */
    public static function sourceShortcutProvider(): array {
        return [
            'path'  => [new Path('name'), Parameter::SOURCE_PATH],
            'query' => [new Query('name'), Parameter::SOURCE_QUERY],
            'body'  => [new Body('name'), Parameter::SOURCE_BODY],
            'file'  => [new File('name'), Parameter::SOURCE_FILE],
        ];
    }

    public function testSourceNameDefaultsToArgumentName(): void {
        self::assertNull(new Query()->name);
        self::assertNull(new Path()->name);
        self::assertNull(new Body()->name);
        self::assertNull(new File()->name);
    }
}
