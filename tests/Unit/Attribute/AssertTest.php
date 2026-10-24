<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Unit\Attribute;

use ApiPlatform\Attribute\Assert\Length;
use ApiPlatform\Attribute\Assert\NotEmpty;
use ApiPlatform\Attribute\Assert\Range;
use ApiPlatform\DTO\ApiEndpointMethodParameterDTO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AssertTest extends TestCase {
    public function testLengthCountsCharacters(): void {
        self::assertTrue(new Length(maxLength: 6)->assert($this->parameter('Привет'))->isSuccessful);
        self::assertFalse(new Length(maxLength: 5)->assert($this->parameter('Привет'))->isSuccessful);
        self::assertTrue(new Length(minLength: 6)->assert($this->parameter('Привет'))->isSuccessful);
        self::assertFalse(new Length(minLength: 7)->assert($this->parameter('Привет'))->isSuccessful);
        self::assertTrue(new Length()->assert($this->parameter(''))->isSuccessful);
    }

    public function testLengthErrors(): void {
        self::assertSame(
            ['title should be at least 3 characters long, 2 given.'],
            new Length(minLength: 3, maxLength: 5)->assert($this->parameter('ab'))->errors
        );
        self::assertSame(
            ['title should be at most 5 characters long, 6 given.'],
            new Length(minLength: 3, maxLength: 5)->assert($this->parameter('abcdef'))->errors
        );
        self::assertSame([], new Length(minLength: 3, maxLength: 5)->assert($this->parameter('abc'))->errors);
    }

    public function testLengthOfNumber(): void {
        self::assertSame(
            ['title should be at most 2 characters long, 3 given.'],
            new Length(maxLength: 2)->assert($this->parameter(123))->errors
        );
    }

    public function testRangeComparesFloats(): void {
        self::assertFalse(new Range(min: 1, max: 1)->assert($this->parameter('1.5'))->isSuccessful);
        self::assertTrue(new Range(min: 1, max: 2)->assert($this->parameter('1.5'))->isSuccessful);
        self::assertTrue(new Range(min: 1, max: 2)->assert($this->parameter('1'))->isSuccessful);
        self::assertTrue(new Range(min: 1, max: 2)->assert($this->parameter(2))->isSuccessful);
        self::assertFalse(new Range(min: 0)->assert($this->parameter('-0.5'))->isSuccessful);
        self::assertTrue(new Range(min: 0.5)->assert($this->parameter('0.5'))->isSuccessful);
        self::assertTrue(new Range()->assert($this->parameter('100500'))->isSuccessful);
    }

    public function testRangeErrors(): void {
        self::assertSame(
            ['title should be 1 or more, 0.5 given.'],
            new Range(min: 1, max: 10)->assert($this->parameter('0.5'))->errors
        );
        self::assertSame(
            ['title should be 10 or less, 11 given.'],
            new Range(min: 1, max: 10)->assert($this->parameter(11))->errors
        );
        self::assertSame(['title should be a number.'], new Range(min: 1)->assert($this->parameter('abc'))->errors);
    }

    #[DataProvider('notEmptyProvider')]
    public function testNotEmpty(mixed $value, bool $expected): void {
        $result = new NotEmpty()->assert($this->parameter($value));

        self::assertSame($expected, $result->isSuccessful);
        self::assertSame($expected ? [] : ['title cannot be empty.'], $result->errors);
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function notEmptyProvider(): array {
        return [
            'null'         => [null, false],
            'empty string' => ['', false],
            // A valid value of a number or a flag.
            'zero'         => ['0', true],
            'false'        => [false, true],
            'text'         => ['a', true],
        ];
    }

    protected function parameter(mixed $value): ApiEndpointMethodParameterDTO {
        return new ApiEndpointMethodParameterDTO('title', 'titleArgument', 'string', false, $value, []);
    }
}
