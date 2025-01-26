<?php

namespace LazyJson\Tests\Unit;

use LazyJson\{
    ArrayElement,
    JsonElement,
    StringElement,
};
use LazyJson\Tests\Unit\Fixtures\TempFileHelper;
use PHPUnit\Framework\Attributes\{
    CoversClass,
    DataProvider,
    Group,
    Large,
    TestDox,
    UsesClass,
};
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

#[TestDox('StringElement')]
#[CoversClass(StringElement::class)]
#[UsesClass(ArrayElement::class)]
#[UsesClass(JsonElement::class)]
#[Large]
class StringElementTest extends TestCase
{
    // Static methods

    public static function jsonStringProvider(): iterable
    {
        yield 'Empty string' => [
            'json' => '""',
        ];

        yield 'Empty string with extra spaces' => [
            'json' => " \r\n\t\"\" \r\n\t",
        ];

        yield 'Simple string' => [
            'json' => '"abc"',
        ];

        yield 'Simple string with extra spaces' => [
            'json' => " \r\n\t\"abc\" \r\n\t",
        ];

        yield 'String with unicode chars and special chars' => [
            'json' => '"\\u00e1lgebra\\nI am happy \\uD83D\\uDE0A"',
        ];
    }

    public static function jsonEscapedSequenceProvider(): iterable
    {
        yield 'double quote' => [
            'json' => '"\\""',
            'expected' => '"',
        ];

        yield 'back slash' => [
            'json' => '"\\\\"',
            'expected' => '\\',
        ];

        yield 'slash' => [
            'json' => '"\\/""',
            'expected' => '/',
        ];

        yield 'back-space' => [
            'json' => '"\\b"',
            'expected' => chr(8),
        ];

        yield 'form feed' => [
            'json' => '"\\f"',
            'expected' => "\f",
        ];

        yield 'line feed' => [
            'json' => '"\\n"',
            'expected' => "\n",
        ];

        yield 'carriage return' => [
            'json' => '"\\r"',
            'expected' => "\r",
        ];

        yield 'tab' => [
            'json' => '"\\t"',
            'expected' => "\t",
        ];

        yield 'Unicode ASCII char' => [
            'json' => '"\\u0061"',
            'expected' => 'a',
        ];

        yield 'Unicode Latin char' => [
            'json' => '"\\u00e7"',
            'expected' => 'ç',
        ];

        yield 'Unicode Kanji char' => [
            'json' => '"\\u6edd"',
            'expected' => '滝',
        ];

        yield 'Unicode Smile char' => [
            'json' => '"\\uD83D\\uDE0A"',
            'expected' => '😊',
        ];
    }

    public static function invalidJsonStringProvider(): iterable
    {
        yield 'without content and missing end of string' => ['"'];
        yield 'with content but missing end of string' => ['"abc'];
        yield 'with invalid escape sequence' => ['"a\\d"'];
        yield 'with incomplete escape sequence' => ['"a\\'];
        yield 'with a control byte' => ['"' . chr(0) . '"'];
        yield 'with incomplete UTF-16 symbol' => ['"\\uD83D"'];
        yield 'with incomplete UTF-16 symbol 2' => ['"\\uD83D\\bDC00"'];
        yield 'with invalid UTF-16 sequence' => ['"\\uGGGG"'];

        yield 'with invalid high surrogate of UTF-16 symbol' => ['"\\uDC00\\uDC00\\"'];
        yield 'with invalid low surrogate of UTF-16 symbol' => ['"\\uD83D\\uDBFF\\"'];
        yield 'with invalid low surrogate of UTF-16 symbol 2' => ['"\\uD83D\\uGGGGG"'];
    }

    // Tests

    #[DataProvider('jsonStringProvider')]
    #[TestDox('loading a JSON with a string must return an StringElement instance.')]
    public function testInstance($json): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);

        // Execute
        $instance = JsonElement::load($file);

        // Expect
        $this->assertInstanceOf(StringElement::class, $instance);
        $this->assertInstanceOf(JsonElement::class, $instance);
    }

    #[DataProvider('jsonStringProvider')]
    #[TestDox('loading a JSON with a string must be able to convert to PHP string.')]
    public function testStringable($json): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Execute
        $value = (string) $instance;

        // Expect
        $this->assertEquals(json_decode($json), $value);
    }

    #[DataProvider('jsonStringProvider')]
    #[TestDox('loading a JSON with a string expects the elements to be a string.')]
    public function testIteratorTypes(string $json): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Execute
        $iterator = $instance->getIterator();

        // Expect
        $this->assertContainsOnly('string', $iterator);
    }

    #[DataProvider('jsonStringProvider')]
    #[TestDox('loading a JSON with a string expects to be possissible to traverse the elements twice.')]
    public function testMultipleTraverseIterator(string $json): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Execute
        $elements1 = iterator_to_array($instance->getIterator());
        $elements2 = iterator_to_array($instance->getIterator());

        // Expect
        $this->assertEquals($elements1, $elements2);
    }

    #[DataProvider('jsonStringProvider')]
    #[TestDox('loading a JSON with a string expects to be possissible to traverse the elements twice, even with disabled cache.')]
    public function testMultipleTraverseWithoutCacheIterator(string $json): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file, false);

        // Execute
        $elements1 = iterator_to_array($instance->getIterator());
        $elements2 = iterator_to_array($instance->getIterator());

        // Expect
        $this->assertEquals($elements1, $elements2);
    }

    #[DataProvider('jsonStringProvider')]
    #[TestDox('loading a JSON with a string expects the decoded elements to be correct, even if the file cursor is moved during the iteration.')]
    public function testIteratorValuesHavingFileCursorMoves(string $json): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Execute
        $iterator = $instance->getIterator();
        $str = '';
        foreach ($instance->getIterator() as $char) {
            $file->fseek(0);
            $str .= $char;
            $file->fseek(0);
        }

        // Expect
        $this->assertEquals(json_decode($json), $str);
    }

    #[DataProvider('jsonStringProvider')]
    #[TestDox('loading a JSON with a string expects the decoded elements to be correct, even if the file cursor is moved during the iteration and cache is disabled.')]
    public function testIteratorValuesHavingFileCursorMovesAndDisabledCache(string $json): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file, false);

        // Execute
        $iterator = $instance->getIterator();
        $str = '';
        foreach ($instance->getIterator() as $char) {
            $file->fseek(0);
            $str .= $char;
            $file->fseek(0);
        }

        // Expect
        $this->assertEquals(json_decode($json), $str);
    }

    #[DataProvider('jsonStringProvider')]
    #[TestDox('loading a JSON with a string expects to be JSON serializable.')]
    public function testJsonSerializable(string $json): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file, false);

        // Execute
        $result = json_encode($instance);

        // Expect
        $this->assertEquals(json_encode(json_decode($json)), $result);
    }

    #[DataProvider('jsonEscapedSequenceProvider')]
    #[TestDox('loading a JSON with a string containing escaped sequences expects to decode the sequence correctly.')]
    public function testEscapedSequences(string $json, string $expected): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file, false);

        // Execute
        $value = $instance->getDecodedValue();

        // Expect
        $this->assertEquals($expected, $value);
    }

    #[DataProvider('invalidJsonStringProvider')]
    #[TestDox('loading a JSON with an invalid string expects to throw an exception.')]
    public function testInvalidJsonString(string $json): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file, false);

        // Expect
        $this->expectException(UnexpectedValueException::class);

        // Execute
        $instance->getDecodedValue();
    }

    #[TestDox('loading a JSON with an array with 2 strings expects to parse the first element to get the second.')]
    public function testReadCurrentJsonElement(): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile('["abc","def"]');
        $instance = JsonElement::load($file, false);

        // Execute
        $value = $instance[1]->getDecodedValue();

        // Expect
        $this->assertEquals('def', $value);
    }

    #[Group('memory')]
    #[Testdox('loading a very big JSON string, it should not increase memory usage more than 1Kb.')]
    public function testMemoryUsage(): void
    {
        // Prepare
        $fileContent = (function () {
            yield '"';
            for ($i = 1; $i <= 10000; $i++) {
                yield md5($i);
            }
            yield '"';
        })();
        $file = TempFileHelper::createTempFile($fileContent);

        // Execute
        $initialMemory = memory_get_usage(true);
        $instance = JsonElement::load($file, false);
        $counter = 0;
        foreach ($instance as $char) {
            $counter += 1;
        }
        $finalMemory = memory_get_usage(true);
        $totalMemoryUsage = $finalMemory - $initialMemory;

        // Expect
        $this->assertEquals(320000, $counter);
        $this->assertLessThan(1024, $totalMemoryUsage); // Less than 1Kb
    }
}