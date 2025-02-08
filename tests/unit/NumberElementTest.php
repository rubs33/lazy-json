<?php

namespace LazyJson\Tests\Unit;

use LazyJson\{
    NumberElement,
    JsonElement,
};
use LazyJson\Tests\Unit\Fixtures\TempFileHelper;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * @testdox \LazyJson\NumberElement
 * @covers \LazyJson\NumberElement
 * @uses \LazyJson\ArrayElement
 * @uses \LazyJson\JsonElement
 */
class NumberElementTest extends TestCase
{
    // Static methods

    public static function jsonExponentProvider(): iterable
    {
        yield 'e0';
        yield 'e1';
        yield 'e0123';
        yield 'e-0123';
        yield 'E0123';
        yield 'E-0123';
    }

    public static function jsonIntProvider(): iterable
    {
        yield '0';
        yield '1';
        yield '9';
        yield '1000';
    }

    public static function jsonDecimalProvider(): iterable
    {
        yield '0';
        yield '1';
        yield '9';
        yield '0123';
    }

    public static function jsonNumberProvider(): iterable
    {
        foreach (['', '-'] as $sign) {

            // Int
            foreach (self::jsonIntProvider() as $n) {
                $number = sprintf('%s%s', $sign, $n);
                yield $number => [$number];
            }

            // Float
            foreach (self::jsonIntProvider() as $n) {
                foreach (self::jsonDecimalProvider() as $d) {
                    $number = sprintf('%s%s.%s', $sign, $n, $d);
                    yield $number => [$number];
                }
            }

            // Int + Exp
            foreach (self::jsonIntProvider() as $n) {
                foreach (self::jsonExponentProvider() as $exp) {
                    $number = sprintf('%s%s%s', $sign, $n, $exp);
                    yield $number => [$number];
                }
            }

            // Float
            foreach (self::jsonIntProvider() as $n) {
                foreach (self::jsonDecimalProvider() as $d) {
                    foreach (self::jsonExponentProvider() as $exp) {
                        $number = sprintf('%s%s.%s%s', $sign, $n, $d, $exp);
                        yield $number => [$number];
                    }
                }
            }
        }

        yield 'number with extra spaces' => [" \t\r\n-1234.5678e0123 \t\r\n"];

        yield 'infinite' => ['1e1000'];
        yield 'negative infinite' => ['-1e1000'];
    }
    public static function invalidJsonNumberProvider(): iterable
    {
        yield '-a' => ['-a'];
        yield '1.a' => ['1.a'];
        yield '-1.a' => ['-1.a'];
        yield '0ea' => ['0ea'];
        yield '0e-a' => ['0e-a'];
        yield '0Ea' => ['0Ea'];
        yield '0E-a' => ['0E-a'];
        yield '-0ea' => ['-0ea'];
        yield '-0e-a' => ['-0e-a'];
        yield '-0Ea' => ['-0Ea'];
        yield '-0E-a' => ['-0E-a'];
    }

    // Tests

    /**
     * @dataProvider jsonNumberProvider
     * @testdox loading a JSON with the value "$json" must return a NumberElement instance.
     */
    public function testInstance(string $json): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);

        // Execute
        $instance = JsonElement::load($file);

        // Expect
        $this->assertInstanceOf(NumberElement::class, $instance);
        $this->assertInstanceOf(JsonElement::class, $instance);
    }

    /**
     * @dataProvider jsonNumberProvider
     * @testdox loading a JSON with the value "$json" must be able to convert to string.
     */
    public function testStringable(string $json): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Execute
        $value = (string) $instance;

        // Expect
        $this->assertEquals(var_export(json_decode($json), true), $value);
    }

    /**
     * @dataProvider jsonNumberProvider
     * @testdox loading a JSON with the value "$json" must be able to decode to a numeric value.
     */
    public function testDecodedValue(string $json): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Execute
        $value = $instance->getDecodedValue();

        // Expect
        $this->assertEquals(json_decode($json), $value);
    }

    /**
     * @dataProvider jsonNumberProvider
     * @testdox loading a JSON with the value "$json" must be able to get raw value.
     */
    public function testGetRawValue(string $json): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Execute
        $value = $instance->getRawValue();

        // Expect
        $this->assertEquals(trim($json), $value);
    }

    /**
     * @dataProvider invalidJsonNumberProvider
     * @testdox loading a JSON with the value "$json" must throw an exception.
     */
    public function testInvalidNumber(string $json): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Expect
        $this->expectException(UnexpectedValueException::class);

        // Execute
        $instance->getDecodedValue();
    }

    /**
     * @testdox loading a JSON with an array with 2 numbers expects to parse the first element to get the second.
     */
    public function testReadCurrentJsonElement(): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile('[1234.5678e-01234,123]');
        $instance = JsonElement::load($file, false);

        // Execute
        $value = $instance[1]->getDecodedValue();

        // Expect
        $this->assertEquals(123, $value);
    }
}
