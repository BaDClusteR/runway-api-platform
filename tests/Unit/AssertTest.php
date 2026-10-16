<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Unit;

use ApiPlatform\Attribute\Assert\Length;
use ApiPlatform\Attribute\Assert\Range;
use ApiPlatform\DTO\ApiEndpointMethodParameterDTO;
use PHPUnit\Framework\TestCase;

class AssertTest extends TestCase {
    public function testLengthCountsCharacters(): void {
        self::assertTrue(new Length(maxLength: 6)->assert($this->parameter('Привет'))->isSuccessful);
        self::assertFalse(new Length(maxLength: 5)->assert($this->parameter('Привет'))->isSuccessful);
        self::assertFalse(new Length(minLength: 7)->assert($this->parameter('Привет'))->isSuccessful);
    }

    public function testRangeComparesFloats(): void {
        self::assertFalse(new Range(min: 1, max: 1)->assert($this->parameter('1.5'))->isSuccessful);
        self::assertTrue(new Range(min: 1, max: 2)->assert($this->parameter('1.5'))->isSuccessful);
        self::assertFalse(new Range(min: 0)->assert($this->parameter('-0.5'))->isSuccessful);
    }

    protected function parameter(mixed $value): ApiEndpointMethodParameterDTO {
        return new ApiEndpointMethodParameterDTO('value', 'value', 'string', false, $value, []);
    }
}
