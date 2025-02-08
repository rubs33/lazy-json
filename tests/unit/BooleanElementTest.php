<?php

namespace LazyJson\Tests\Unit;

use LazyJson\{
    BooleanElement,
    JsonElement,
};
use LazyJson\Tests\Unit\Fixtures\TempFileHelper;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * @testdox \LazyJson\BooleanElement
 * @covers \LazyJson\BooleanElement
 * @uses \LazyJson\ArrayElement
 * @uses \LazyJson\JsonElement
 */
class BooleanElementTest extends TestCase
{
    // Static methods

    public static function jsonBooleanProvider(): iterable
    {
        yield 'true' => ['true'];
        yield 'true with whitespaces' => [" \t\r\ntrue\t\r\n "];
        yield 'false' => ['false'];
        yield 'false with whitespaces' => [" \t\r\nfalse\t\r\n "];
    }

    public static function jsonBooleanWithDecodedValuesProvider(): iterable
    {
        yield 'true' => [
            'json' => 'true',
            'expected' => true,
        ];
        yield 'true with whitespaces' => [
            'json' => " \t\r\ntrue\t\r\n ",
            'expected' => true,
        ];
        yield 'false' => [
            'json' => 'false',
            'expected' => false,
        ];
        yield 'false with whitespaces' => [
            'json' => " \t\r\nfalse\t\r\n ",
            'expected' => false,
        ];
    }

    public static function invalidJsonBooleanProvider(): iterable
    {
        yield 't' => ['t'];
        yield 'tr' => ['tr'];
        yield 'tru' => ['tru'];
        yield 'trrue' => ['trrue'];
        yield 'f' => ['f'];
        yield 'fa' => ['fa'];
        yield 'fal' => ['fal'];
        yield 'fals' => ['fals'];
        yield 'falsse' => ['falsse'];
        yield 'falsy' => ['falsy'];
    }

    // Tests

    /**
     * @dataProvider jsonBooleanProvider
     * @testdox loading a JSON with the value "$json" must return a BooleanElement instance.
     */
    public function testInstance(string $json): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);

        // Execute
        $instance = JsonElement::load($file);

        // Expect
        $this->assertInstanceOf(BooleanElement::class, $instance);
        $this->assertInstanceOf(JsonElement::class, $instance);
    }

    /**
     * @dataProvider jsonBooleanWithDecodedValuesProvider
     * @testdox loading a JSON with the value "$json" must be able to convert to string.
     */
    public function testStringable(string $json, bool $expected): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Execute
        $value = (string) $instance;

        // Expect
        $this->assertEquals($expected ? 'true' : 'false', $value);
    }

    /**
     * @dataProvider jsonBooleanWithDecodedValuesProvider
     * @testdox loading a JSON with the value "$json" must be able to decode to a boolean value.
     */
    public function testDecodedValue(string $json, bool $expected): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Execute
        $value = $instance->getDecodedValue();

        // Expect
        $this->assertEquals($expected, $value);
    }

    /**
     * @testdox loading a JSON with an array with 2 booleans expects to parse the first element to get the second.
     */
    public function testReadCurrentJsonElement(): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile('[false, true]');
        $instance = JsonElement::load($file, false);

        // Execute
        $value = $instance[1]->getDecodedValue();

        // Expect
        $this->assertEquals(true, $value);
    }

    /**
     * @dataProvider invalidJsonBooleanProvider
     * @testdox loading a JSON with the value "$json" must throw an exception.
     */
    public function testInvalidBoolean(string $json): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Expect
        $this->expectException(UnexpectedValueException::class);

        // Execute
        $instance->getDecodedValue();
    }
}
