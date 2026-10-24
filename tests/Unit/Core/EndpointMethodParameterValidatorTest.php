<?php

declare(strict_types=1);

namespace ApiPlatform\Tests\Unit\Core;

use ApiPlatform\Attribute\Assert\IAssertion;
use ApiPlatform\Attribute\Assert\Length;
use ApiPlatform\Attribute\Assert\NotEmpty;
use ApiPlatform\Core\Validator\EndpointMethodParameterValidator;
use ApiPlatform\DTO\ApiEndpointArgumentFileDTO;
use ApiPlatform\DTO\ApiEndpointMethodParameterDTO;
use ApiPlatform\DTO\ApiParameterValidationResultDTO;
use ApiPlatform\Exception\InternalErrorException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EndpointMethodParameterValidatorTest extends TestCase {
    /**
     * @param string[] $errors
     */
    #[DataProvider('valueProvider')]
    public function testValue(string $type, mixed $value, array $errors): void {
        $result = new EndpointMethodParameterValidator()->validate($this->parameter($type, $value));

        self::assertSame($errors, $result->errors);
        self::assertSame($errors === [], $result->isValidated);
    }

    /**
     * @return array<string, array{string, mixed, string[]}>
     */
    public static function valueProvider(): array {
        return [
            'int'                => ['int', '42', []],
            'negative int'       => ['int', -42, []],
            'int as float'       => ['int', '42.0', []],
            'int exponent'       => ['int', '1e3', []],
            'float for int'      => ['int', '1.5', ['number is expected to be integer, float given (1.5).']],
            'float with zero'    => ['int', '-1.50', ['number is expected to be integer, float given (-1.5).']],
            'text for int'       => ['int', 'abc', ['number is not a number.']],
            'empty for int'      => ['int', '', ['number is not a number.']],
            'array for int'      => ['int', [1], ['number is not a number.']],
            'null for int'       => ['int', null, ['number is not a number.']],
            'float'              => ['float', '1.5', []],
            'int for float'      => ['float', 2, []],
            'text for float'     => ['float', '1.5a', ['number is not a number.']],
            'bool'               => ['bool', true, []],
            'bool string'        => ['bool', 'false', []],
            'bool yes'           => ['bool', 'yes', []],
            'bool zero'          => ['bool', '0', []],
            'bool int'           => ['bool', 1, []],
            'text for bool'      => ['bool', 'abc', ['number is not a boolean.']],
            'array for bool'     => ['bool', ['1'], ['number is not a boolean.']],
            'string'             => ['string', 'abc', []],
            'number for string'  => ['string', 42, []],
            'array for string'   => ['string', ['a'], ['number is not a string.']],
            'null for string'    => ['string', null, ['number is not a string.']],
            'array'              => ['array', ['a' => 1], []],
            'empty array'        => ['array', [], []],
            'string for array'   => ['array', 'a', ['number is not an array.']],
            'file'               => [ApiEndpointArgumentFileDTO::class, new ApiEndpointArgumentFileDTO('a', 'b', 'c', 1), []],
        ];
    }

    public function testNullOfNullable(): void {
        $result = new EndpointMethodParameterValidator()->validate($this->parameter('int', null, true, [new NotEmpty()]));

        self::assertTrue($result->isValidated);
        self::assertSame([], $result->errors);
    }

    public function testNullableIsChecked(): void {
        $result = new EndpointMethodParameterValidator()->validate($this->parameter('int', 'abc', true));

        self::assertSame(['number is not a number.'], $result->errors);
    }

    #[DataProvider('unsupportedTypeProvider')]
    public function testUnsupportedType(string $type): void {
        $this->expectException(InternalErrorException::class);
        $this->expectExceptionMessage(
            "numberArgument has incorrect type '$type'. Allowed types are bool, int, float, string, array, "
            . ApiEndpointArgumentFileDTO::class . '.'
        );

        new EndpointMethodParameterValidator()->validate($this->parameter($type, 'a'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsupportedTypeProvider(): array {
        return [
            'no type' => [''],
            'mixed'   => ['mixed'],
            'union'   => ['string|int'],
            'object'  => ['stdClass'],
        ];
    }

    public function testAssertions(): void {
        $result = new EndpointMethodParameterValidator()->validate(
            $this->parameter('string', '', false, [new NotEmpty(), new Length(minLength: 2)])
        );

        self::assertFalse($result->isValidated);
        self::assertSame(
            ['number cannot be empty.', 'number should be at least 2 characters long, 0 given.'],
            $result->errors
        );
    }

    public function testAssertionWithSeveralErrors(): void {
        $assertion = $this->createStub(IAssertion::class);
        $assertion->method('assert')->willReturn(new ApiParameterValidationResultDTO(false, ['a', 'b']));

        $result = new EndpointMethodParameterValidator()->validate($this->parameter('string', 'x', false, [$assertion]));

        self::assertSame(['a', 'b'], $result->errors);
    }

    public function testSuccessfulAssertion(): void {
        $result = new EndpointMethodParameterValidator()->validate(
            $this->parameter('string', 'ab', false, [new Length(minLength: 2)])
        );

        self::assertTrue($result->isValidated);
    }

    /**
     * The assertions expect a value of the type: e.g. Length of an array would be "Array to string conversion".
     */
    public function testAssertionsOfInvalidType(): void {
        $assertion = $this->createMock(IAssertion::class);
        $assertion->expects($this->never())->method('assert');

        $result = new EndpointMethodParameterValidator()->validate($this->parameter('string', ['a'], false, [$assertion]));

        self::assertSame(['number is not a string.'], $result->errors);
    }

    public function testErrorsOfPreviousParameterAreNotKept(): void {
        $validator = new EndpointMethodParameterValidator();
        $validator->validate($this->parameter('int', 'abc'));

        self::assertTrue($validator->validate($this->parameter('int', '1'))->isValidated);
    }

    /**
     * @param IAssertion[] $assertions
     */
    protected function parameter(
        string $type,
        mixed  $value,
        bool   $isNullable = false,
        array  $assertions = []
    ): ApiEndpointMethodParameterDTO {
        return new ApiEndpointMethodParameterDTO('number', 'numberArgument', $type, $isNullable, $value, $assertions);
    }
}
