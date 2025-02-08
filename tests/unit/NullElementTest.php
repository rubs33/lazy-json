<?php

namespace LazyJson\Tests\Unit;

use LazyJson\{
    NullElement,
    JsonElement,
};
use LazyJson\Tests\Unit\Fixtures\TempFileHelper;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * @testdox \LazyJson\NullElement
 * @covers \LazyJson\NullElement
 * @uses \LazyJson\ArrayElement
 * @uses \LazyJson\JsonElement
 */
class NullElementTest extends TestCase
{
    // Static methods

    public static function jsonNullProvider(): iterable
    {
        yield 'null' => ['null'];
        yield 'null with whitespaces' => [" \t\r\nnull\t\r\n "];
    }

    public static function invalidJsonNullProvider(): iterable
    {
        yield 'n' => ['n'];
        yield 'nu' => ['nu'];
        yield 'nul' => ['nul'];
        yield 'nil' => ['nil'];
        yield 'nuul' => ['nuul'];
    }

    // Tests

    /**
     * @dataProvider jsonNullProvider
     * @testdox loading a JSON with the value "$json" must return a NullElement instance.
     */
    public function testInstance(string $json): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);

        // Execute
        $instance = JsonElement::load($file);

        // Expect
        $this->assertInstanceOf(NullElement::class, $instance);
        $this->assertInstanceOf(JsonElement::class, $instance);
    }

    /**
     * @dataProvider jsonNullProvider
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
        $this->assertEquals('null', $value);
    }

    /**
     * @dataProvider jsonNullProvider
     * @testdox loading a JSON with the value "$json" must be able to decode to null.
     */
    public function testDecodedValue(string $json): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Execute
        $value = $instance->getDecodedValue();

        // Expect
        $this->assertEquals(null, $value);
    }

    /**
     * @dataProvider invalidJsonNullProvider
     * @testdox loading a JSON with the value "$json" must throw an exception.
     */
    public function testInvalidNull(string $json): void
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
     * @testdox loading a JSON with an array with 2 null expects to parse the first element to get the second.
     */
    public function testReadCurrentJsonElement(): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile('[null, null]');
        $instance = JsonElement::load($file, false);

        // Execute
        $value = $instance[1]->getDecodedValue();

        // Expect
        $this->assertEquals(null, $value);
    }
}
