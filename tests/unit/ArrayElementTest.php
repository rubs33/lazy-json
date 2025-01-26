<?php

namespace LazyJson\Tests\Unit;

use LazyJson\{
    ArrayElement,
    BooleanElement,
    JsonElement,
    NumberElement,
    NullElement,
    ObjectElement,
    StringElement,
};
use LazyJson\Tests\Unit\Fixtures\TempFileHelper;
use LogicException;
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

#[TestDox('ArrayElement')]
#[CoversClass(ArrayElement::class)]
#[UsesClass(BooleanElement::class)]
#[UsesClass(JsonElement::class)]
#[UsesClass(NumberElement::class)]
#[UsesClass(NullElement::class)]
#[UsesClass(ObjectElement::class)]
#[UsesClass(StringElement::class)]
#[Large]
class ArrayElementTest extends TestCase
{
    // Static methods

    public static function jsonArrayProvider(): iterable
    {
        yield 'Empty array' => [
            'json' => '[]',
        ];

        yield 'Empty array with extra spaces' => [
            'json' => " \r\n\t[ \r\n\t] \r\n\t",
        ];

        yield 'Simple array with numbers' => [
            'json' => '[1,2,3]',
        ];

        yield 'Simple array with numbers and extra spaces' => [
            'json' => " \r\n\t[ \r\n\t1 \r\n\t, \r\n\t2 \r\n\t, \r\n\t3 \r\n\t] \r\n\t",
        ];

        yield 'Complex array with all types of elements' => [
            'json' => '["foo",1,3.14,true,false,null,{"foo":"bar"},["baz"]]',
        ];
    }

    public static function jsonArrayWithDecodedValuesProvider(): iterable
    {
        yield 'Empty array' => [
            'json' => '[]',
            'expected' => [],
        ];

        yield 'Empty array with extra spaces' => [
            'json' => " \r\n\t[ \r\n\t] \r\n\t",
            'expected' => [],
        ];

        yield 'Simple array with numbers' => [
            'json' => '[1,2,3]',
            'expected' => [1, 2, 3],
        ];

        yield 'Simple array with numbers and extra spaces' => [
            'json' => " \r\n\t[ \r\n\t1 \r\n\t, \r\n\t2 \r\n\t, \r\n\t3 \r\n\t] \r\n\t",
            'expected' => [1, 2, 3],
        ];

        yield 'Complex array with all types of elements' => [
            'json' => '["foo",1,3.14,true,false,null,{"foo":"bar"},["baz"]]',
            'expected' => ['foo', 1, 3.14, true, false, null, (object) ['foo' => 'bar'], ['baz']],
        ];
    }

    public static function nonEmptyJsonArrayWithValuesProvider(): iterable
    {
        foreach (self::jsonArrayWithDecodedValuesProvider() as $key => $value) {
            if (!empty($value['expected'])) {
                yield $key => $value;
            }
        }
    }

    public static function invalidJsonArrayProvider(): iterable
    {
        yield 'without content and missing end of array' => ['['];
        yield 'with content but missing end of array' => ['[1'];
        yield 'missing end of string value' => ['["x'];
        yield 'missing comma' => ['[1 2 3]'];
        yield 'missing second value' => ['[1,]'];
    }

    // Tests

    #[DataProvider('jsonArrayProvider')]
    #[TestDox('loading a JSON with an array must return an ArrayElement instance.')]
    public function testInstance($json): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);

        // Execute
        $instance = JsonElement::load($file);

        // Expect
        $this->assertInstanceOf(ArrayElement::class, $instance);
        $this->assertInstanceOf(JsonElement::class, $instance);
    }

    #[DataProvider('jsonArrayProvider')]
    #[TestDox('loading a JSON with an array must be able to convert to string.')]
    public function testStringable($json): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Execute
        $value = (string) $instance;

        // Expect
        $this->assertEquals('Array', $value);
    }

    #[DataProvider('jsonArrayProvider')]
    #[TestDox('loading a JSON with an array expects the elements to be a JsonElement.')]
    public function testIteratorTypes(string $json): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Execute
        $iterator = $instance->getIterator();

        // Expect
        $this->assertContainsOnlyInstancesOf(JsonElement::class, $iterator);
    }

    #[DataProvider('jsonArrayProvider')]
    #[TestDox('loading a JSON with an array expects to be possissible to traverse the elements twice.')]
    public function testMultipleTraverseIterator(string $json): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Execute
        $elements1 = array_map(
            static fn(JsonElement $element): mixed => $element->getDecodedValue(),
            iterator_to_array($instance->getIterator()),
        );
        $elements2 = array_map(
            static fn(JsonElement $element): mixed => $element->getDecodedValue(),
            iterator_to_array($instance->getIterator()),
        );

        // Expect
        $this->assertEquals($elements1, $elements2);
    }

    #[DataProvider('jsonArrayProvider')]
    #[TestDox('loading a JSON with an array expects to be possissible to traverse the elements twice, even with disabled cache.')]
    public function testMultipleTraverseWithoutCacheIterator(string $json): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file, false);

        // Execute
        $elements1 = array_map(
            static fn(JsonElement $element): mixed => $element->getDecodedValue(),
            iterator_to_array($instance->getIterator()),
        );
        $elements2 = array_map(
            static fn(JsonElement $element): mixed => $element->getDecodedValue(),
            iterator_to_array($instance->getIterator()),
        );

        // Expect
        $this->assertEquals($elements1, $elements2);
    }

    #[DataProvider('jsonArrayWithDecodedValuesProvider')]
    #[TestDox('loading a JSON with an array expects to be possible to decode elements.')]
    public function testIteratorValues(string $json, array $expected): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Execute
        $iterator = $instance->getIterator();
        $elements = array_map(
            static fn(JsonElement $element): mixed => $element->getDecodedValue(),
            iterator_to_array($iterator),
        );

        // Expect
        $this->assertEquals($expected, $elements);
    }

    #[DataProvider('jsonArrayWithDecodedValuesProvider')]
    #[TestDox('loading a JSON with an array expects the decoded elements to be equal $expected, even if the file cursor is moved during the iteration.')]
    public function testIteratorValuesHavingFileCursorMoves(string $json, array $expected): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Execute
        $iterator = $instance->getIterator();
        $elements = [];
        foreach ($instance->getIterator() as $element) {
            $file->fseek(0);
            $elements[] = $element->getDecodedValue();
            $file->fseek(0);
        }

        // Expect
        $this->assertEquals($expected, $elements);
    }

    #[DataProvider('jsonArrayWithDecodedValuesProvider')]
    #[TestDox('loading a JSON with an array expects the decoded elements to be equal $expected, even if the file cursor is moved during the iteration and cache is disabled.')]
    public function testIteratorValuesHavingFileCursorMovesAndDisabledCache(string $json, array $expected): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file, false);

        // Execute
        $iterator = $instance->getIterator();
        $elements = [];
        foreach ($instance->getIterator() as $element) {
            $file->fseek(0);
            $elements[] = $element->getDecodedValue();
            $file->fseek(0);
        }

        // Expect
        $this->assertEquals($expected, $elements);
    }

    #[DataProvider('nonEmptyJsonArrayWithValuesProvider')]
    #[TestDox('loading a JSON with an array expects to acess random elements correctly.')]
    public function testOffsetGet(string $json, array $expected): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Execute
        foreach ($expected as $i => $value) {

            // Expect
            $this->assertEquals($value, $instance[$i]->getDecodedValue());
        }

        // Check if an already fetched value continue returning the same value
        foreach ($expected as $i => $value) {
            $this->assertEquals($value, $instance[$i]->getDecodedValue());
        }

        // Check if an invalid position returns null
        $this->assertEquals(null, $instance[count($expected)]);
        $this->assertEquals(null, $instance['a']);
    }

    #[DataProvider('nonEmptyJsonArrayWithValuesProvider')]
    #[TestDox('loading a JSON with an array expects to acess random elements correctly, even if cache is disabled.')]
    public function testOffsetGetHavingDisabledCache(string $json, array $expected): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file, false);

        // Execute
        foreach ($expected as $i => $value) {

            // Expect
            $this->assertEquals($value, $instance[$i]->getDecodedValue());
        }

        // Check if an invalid position returns null
        $this->assertEquals(null, $instance[count($expected)]);
        $this->assertEquals(null, $instance['a']);
    }

    #[DataProvider('nonEmptyJsonArrayWithValuesProvider')]
    #[TestDox('loading a JSON with an array expects to be able to check if offset exists.')]
    public function testOffsetExists(string $json, array $expected): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Execute
        foreach ($expected as $i => $value) {

            // Expect
            $this->assertEquals(true, isset($instance[$i]));
        }
        $this->assertEquals(false, isset($instance[count($expected)]));
        $this->assertEquals(false, isset($instance['a']));
    }

    #[DataProvider('nonEmptyJsonArrayWithValuesProvider')]
    #[TestDox('loading a JSON with an array expects to be able to check if offset exists, even if cache is disabled.')]
    public function testOffsetExistsHavingDisabledCache(string $json, array $expected): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file, false);

        // Execute
        foreach ($expected as $i => $value) {

            // Expect
            $this->assertEquals(true, isset($instance[$i]));
        }
        $this->assertEquals(false, isset($instance[count($expected)]));
        $this->assertEquals(false, isset($instance['a']));
    }

    #[TestDox('loading a JSON with an array expects to be able to check if offset exists using cache.')]
    public function testOffsetExistsCache(): void
    {
        // Prepare
        $json = json_encode([1, 2, 3]);
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Access the first element (the total elements will not be set, but it is possible to determine if the element 0 exists
        $foo = $instance[0];

        $this->assertEquals(true, isset($instance[0]));

        // Traverse the interator to fill totalElements
        foreach ($instance as $value) {
            // Ignore $value
        }

        $this->assertEquals(true, isset($instance[2]));
    }

    #[TestDox('loading a JSON with an array expects to throw an exeption if trying to set an element to an offset.')]
    public function testOffsetSet(): void
    {
        // Prepare
        $json = '[]';
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Expect
        $this->expectException(LogicException::class);

        // Execute
        $instance[0] = true;
    }

    #[TestDox('loading a JSON with an array expects to throw an exeption if trying to unset an element of an offset.')]
    public function testOffsetUnset(): void
    {
        // Prepare
        $json = '[1]';
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Expect
        $this->expectException(LogicException::class);

        // Execute
        unset($instance[0]);
    }

    #[DataProvider('jsonArrayWithDecodedValuesProvider')]
    #[TestDox('loading a JSON with an array expects to check the count correctly.')]
    public function testCount(string $json, array $expected): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Execute
        $count = count($instance);

        // Expect
        $this->assertEquals(count($expected), $count);
    }

    #[DataProvider('jsonArrayWithDecodedValuesProvider')]
    #[TestDox('loading a JSON with an array expects to check the count twice correctly.')]
    public function testCountTwice(string $json, array $expected): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Execute
        $count1 = count($instance);
        $count2 = count($instance);

        // Expect
        $this->assertEquals(count($expected), $count1);
        $this->assertEquals(count($expected), $count2);
    }

    #[DataProvider('jsonArrayWithDecodedValuesProvider')]
    #[TestDox('loading a JSON with an array expects to check the count twice correctly, even if cache is disabled.')]
    public function testCountTwiceHavingDisabledCache(string $json, array $expected): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file, false);

        // Execute
        $count1 = count($instance);
        $count2 = count($instance);

        // Expect
        $this->assertEquals(count($expected), $count1);
        $this->assertEquals(count($expected), $count2);
    }

    #[DataProvider('jsonArrayWithDecodedValuesProvider')]
    #[TestDox('loading a JSON with an array expects to be JSON serializable.')]
    public function testJsonSerializable(string $json, array $expected): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file, false);

        // Execute
        $result = json_encode($instance);

        // Expect
        $this->assertEquals(json_encode($expected), $result);
    }

    #[DataProvider('invalidJsonArrayProvider')]
    #[TestDox('loading a JSON with an invalid array expects to throw an exception.')]
    public function testInvalidJsonArray(string $json): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file, false);

        // Expect
        $this->expectException(UnexpectedValueException::class);

        // Execute
        $instance->getDecodedValue();
    }

    #[Group('memory')]
    #[Testdox('loading a very big JSON array, it should not increase memory usage more than 1Kb.')]
    public function testMemoryUsage(): void
    {
        // Prepare
        $fileContent = (function () {
            yield '[';
            $comma = '';
            for ($i = 1; $i <= 10000; $i++) {
                yield sprintf('%s"%s"', $comma, md5($i));
                $comma = ',';
            }
            yield ']';
        })();
        $file = TempFileHelper::createTempFile($fileContent);

        // Execute
        $initialMemory = memory_get_usage(true);
        $instance = JsonElement::load($file, false);
        $counter = 0;
        foreach ($instance as $element) {
            $counter += 1;
        }
        $finalMemory = memory_get_usage(true);
        $totalMemoryUsage = $finalMemory - $initialMemory;

        $lastElement = $element->getDecodedValue();

        // Expect
        $this->assertEquals(10000, $counter);
        $this->assertEquals(md5('10000'), $lastElement);
        $this->assertLessThan(1024, $totalMemoryUsage); // Less than 1Kb
    }
}