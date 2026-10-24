<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Support;

use PHPUnit\Framework\Assert;

/**
 * The values of the decoded JSON by their paths: a missing key fails the test with the path.
 */
trait ArrayPath {
    protected static function at(mixed $data, int|string ...$keys): mixed {
        $path = '';

        foreach ($keys as $key) {
            Assert::assertIsArray($data, "Not an array at \"$path\".");
            Assert::assertArrayHasKey($key, $data, "No \"$key\" at \"$path\".");

            $data = $data[$key];
            $path .= "/$key";
        }

        return $data;
    }

    /**
     * @return array<mixed>
     */
    protected static function arrayAt(mixed $data, int|string ...$keys): array {
        $value = static::at($data, ...$keys);
        Assert::assertIsArray($value, 'Not an array at "/' . implode('/', $keys) . '".');

        return $value;
    }
}
