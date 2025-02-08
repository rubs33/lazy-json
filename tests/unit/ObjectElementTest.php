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

#[TestDox('ObjectElement')]
#[CoversClass(ObjectElement::class)]
#[UsesClass(ArrayElement::class)]
#[UsesClass(BooleanElement::class)]
#[UsesClass(JsonElement::class)]
#[UsesClass(NumberElement::class)]
#[UsesClass(NullElement::class)]
#[UsesClass(ObjectElement::class)]
#[UsesClass(StringElement::class)]
#[Large]
class ObjectElementTest extends TestCase
{
    // Static methods

    public static function jsonObjectProvider(): iterable
    {
        yield 'Empty object' => [
            'json' => '{}',
        ];

        yield 'Empty object with extra spaces' => [
            'json' => " \r\n\t{ \r\n\t} \r\n\t",
        ];

        yield 'Simple object with numbers' => [
            'json' => '{"x": 1,"y":2}',
        ];

        yield 'Simple object with numbers and extra spaces' => [
            'json' => " \r\n\t{ \r\n\t\"x\" \r\n\t: \r\n\t1 \r\n\t, \r\n\t\"y\" \r\n\t: \r\n\t2 \r\n\t} \r\n\t",
        ];

        yield 'Complex object with all types of elements' => [
            'json' => '{"str":"foo","int":1,"float":3.14,"bool1":true,"bool2":false,"null":null,"obj":{"foo":"bar"},"arr":["baz"]}',
        ];
    }

    public static function jsonObjectWithDecodedValuesProvider(): iterable
    {
        yield 'Empty object' => [
            'json' => '{}',
            'expected' => (object) [],
        ];

        yield 'Empty object with extra spaces' => [
            'json' => " \r\n\t{ \r\n\t} \r\n\t",
            'expected' => (object) [],
        ];

        yield 'Simple object with numbers' => [
            'json' => '{"x":1,"y":2}',
            'expected' => (object) ['x' => 1, 'y' => 2],
        ];

        yield 'Simple object with numbers and extra spaces' => [
            'json' => " \r\n\t{ \r\n\t\"x\" \r\n\t: \r\n\t1 \r\n\t, \r\n\t\"y\" \r\n\t: \r\n\t2 \r\n\t} \r\n\t",
            'expected' => (object) ['x' => 1, 'y' => 2],
        ];

        yield 'Complex object with all types of elements' => [
            'json' => '{"str":"foo","int":1,"float":3.14,"bool1":true,"bool2":false,"null":null,"obj":{"foo":"bar"},"arr":["baz"]}',
            'expected' => (object) [
                'str' => 'foo',
                'int' => 1,
                'float' => 3.14,
                'bool1' => true,
                'bool2' => false,
                'null' => null,
                'obj' => (object) ['foo' => 'bar'],
                'arr' => ['baz'],
            ],
        ];
    }

    public static function invalidJsonObjectProvider(): iterable
    {
        yield 'without content and missing end of object' => ['{'];
        yield 'with content but missing end of object' => ['{"x":1'];
        yield 'with content but missing end of object 2' => ['{"x":1,"y":2'];
        yield 'missing end of property' => ['{"x'];
        yield 'missing property' => ['{1}'];
        yield 'missing value' => ['{"x":}'];
        yield 'missing end of value' => ['{"x":"'];
        yield 'with invalid second element' => ['{"x":1 - }'];
        yield 'missing colon' => ['{"x"1}'];
        yield 'missing second property' => ['{"x":1,}'];
        yield 'missing second value' => ['{"x":1,"y"}'];
        yield 'missing second colon' => ['{"x":1,"y"2}'];
    }

    public static function nonEmptyJsonObjectWithValuesProvider(): iterable
    {
        foreach (self::jsonObjectWithDecodedValuesProvider() as $key => $value) {
            if (!empty((array) $value['expected'])) {
                yield $key => $value;
            }
        }
    }

    // Tests

    #[DataProvider('jsonObjectProvider')]
    #[TestDox('loading a JSON with an object must return an ObjectElement instance.')]
    public function testInstance($json): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);

        // Execute
        $instance = JsonElement::load($file);

        // Expect
        $this->assertInstanceOf(ObjectElement::class, $instance);
        $this->assertInstanceOf(JsonElement::class, $instance);
    }

    #[DataProvider('jsonObjectProvider')]
    #[Testdox('loading a JSON with an object must be able to convert to string.')]
    public function testStringable($json): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Execute
        $value = (string) $instance;

        // Expect
        $this->assertEquals('Object', $value);
    }

    #[DataProvider('jsonObjectProvider')]
    #[TestDox('loading a JSON with an object expects the elements to be a JsonElement.')]
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

    #[DataProvider('jsonObjectProvider')]
    #[TestDox('loading a JSON with an object expects to be possissible to traverse the elements twice.')]
    public function testMultipleTraverseIterator(string $json): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Execute
        $elements1 = (object) array_map(
            static fn(JsonElement $element): mixed => $element->getDecodedValue(),
            iterator_to_array($instance->getIterator()),
        );
        $elements2 = (object) array_map(
            static fn(JsonElement $element): mixed => $element->getDecodedValue(),
            iterator_to_array($instance->getIterator()),
        );

        // Expect
        $this->assertEquals($elements1, $elements2);
    }

    #[DataProvider('jsonObjectProvider')]
    #[TestDox('loading a JSON with an object expects to be possissible to traverse the elements twice, even with disabled cache.')]
    public function testMultipleTraverseWithoutCacheIterator(string $json): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file, false);

        // Execute
        $elements1 = (object) array_map(
            static fn(JsonElement $element): mixed => $element->getDecodedValue(),
            iterator_to_array($instance->getIterator()),
        );
        $elements2 = (object) array_map(
            static fn(JsonElement $element): mixed => $element->getDecodedValue(),
            iterator_to_array($instance->getIterator()),
        );

        // Expect
        $this->assertEquals($elements1, $elements2);
    }

    #[DataProvider('jsonObjectWithDecodedValuesProvider')]
    #[TestDox('loading a JSON with an object expects to be possible to decode elements.')]
    public function testIteratorValues(string $json, object $expected): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Execute
        $iterator = $instance->getIterator();
        $elements = (object) array_map(
            static fn(JsonElement $element): mixed => $element->getDecodedValue(),
            iterator_to_array($iterator),
        );

        // Expect
        $this->assertEquals($expected, $elements);
    }

    #[DataProvider('jsonObjectWithDecodedValuesProvider')]
    #[TestDox('loading a JSON with an object expects the decoded elements to be equal $expected, even if the file cursor is moved during the iteration.')]
    public function testIteratorValuesHavingFileCursorMoves(string $json, object $expected): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Execute
        $iterator = $instance->getIterator();
        $elements = (object) [];
        foreach ($instance->getIterator() as $prop => $element) {
            $file->fseek(0);
            $elements->$prop = $element->getDecodedValue();
            $file->fseek(0);
        }

        // Expect
        $this->assertEquals($expected, $elements);
    }

    #[DataProvider('jsonObjectWithDecodedValuesProvider')]
    #[TestDox('loading a JSON with an object expects the decoded elements to be equal $expected, even if the file cursor is moved during the iteration and cache is disabled.')]
    public function testIteratorValuesHavingFileCursorMovesAndDisabledCache(string $json, object $expected): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file, false);

        // Execute
        $iterator = $instance->getIterator();
        $elements = (object) [];
        foreach ($instance->getIterator() as $prop => $element) {
            $file->fseek(0);
            $elements->$prop = $element->getDecodedValue();
            $file->fseek(0);
        }

        // Expect
        $this->assertEquals($expected, $elements);
    }

    #[TestDox('loading a JSON with an object expects to be possible to decode it as a PHP object')]
    public function testDecodeValueAsObject(): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile('{"x":1}');
        $instance = JsonElement::load($file);

        // Execute
        $decodedObject = $instance->getDecodedValue();

        // Expect
        $this->assertEquals((object) ['x' => 1], $decodedObject);
    }

    #[TestDox('loading a JSON with an object expects to be possible to decode it as a PHP associative array')]
    public function testDecodeValueAsAssociativeArray(): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile('{"x":1}');
        $instance = JsonElement::load($file);

        // Execute
        $decodedArray = $instance->getDecodedValue(true);

        // Expect
        $this->assertEquals(['x' => 1], $decodedArray);
    }

    #[DataProvider('nonEmptyJsonObjectWithValuesProvider')]
    #[TestDox('loading a JSON with an object expects to acess random elements correctly.')]
    public function testOffsetGet(string $json, object $expected): void
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
        $this->assertEquals(null, $instance[0]);
        $this->assertEquals(null, $instance['invalid_position']);
    }

    #[DataProvider('nonEmptyJsonObjectWithValuesProvider')]
    #[TestDox('loading a JSON with an object expects to acess random elements correctly, even if cache is disabled.')]
    public function testOffsetGetHavingDisabledCache(string $json, object $expected): void
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
        $this->assertEquals(null, $instance[0]);
        $this->assertEquals(null, $instance['invalid_position']);
    }

    #[DataProvider('nonEmptyJsonObjectWithValuesProvider')]
    #[TestDox('loading a JSON with an object expects to be able to check if offset exists.')]
    public function testOffsetExists(string $json, object $expected): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Execute
        foreach ($expected as $i => $value) {

            // Expect
            $this->assertEquals(true, isset($instance[$i]));
        }

        $this->assertEquals(false, isset($instance[0]));
        $this->assertEquals(false, isset($instance['invalid_position']));
    }

    #[DataProvider('nonEmptyJsonObjectWithValuesProvider')]
    #[TestDox('loading a JSON with an object expects to be able to check if offset exists, even if cache is disabled.')]
    public function testOffsetExistsHavingDisabledCache(string $json, object $expected): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file, false);

        // Execute
        foreach ($expected as $i => $value) {

            // Expect
            $this->assertEquals(true, isset($instance[$i]));
        }
        $this->assertEquals(false, isset($instance[0]));
        $this->assertEquals(false, isset($instance['invalid_position']));
    }

    #[TestDox('loading a JSON with an object expects to be able to check if offset exists using cache.')]
    public function testOffsetExistsCache(): void
    {
        // Prepare
        $json = '{"x": 1, "y": 2, "z": 3}';
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Access the first element: it will be possible to check if element "x" exists
        $foo = $instance['x'];
        $this->assertEquals(true, isset($instance['x']));

        // Checking position "y": the cursor will advance until it finds element "y", but it will not read the entire object
        $this->assertEquals(true, isset($instance['y']));

        // After traversing the object: all elements can be checked from cache
        foreach ($instance as $value) {
            // itnore
        }

        $this->assertEquals(true, isset($instance['z']));

        $this->assertEquals(false, isset($instance['invalid_position']));
    }

    #[DataProvider('nonEmptyJsonObjectWithValuesProvider')]
    #[TestDox('loading a JSON with an object expects to be able to check if property exists.')]
    public function testIsset(string $json, object $expected): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Execute
        foreach ($expected as $i => $value) {

            // Expect
            $this->assertEquals(true, isset($instance->$i));
        }

        $this->assertEquals(false, isset($instance->invalid_position));
    }

    #[DataProvider('nonEmptyJsonObjectWithValuesProvider')]
    #[Testdox('loading a JSON with an object expects to be able to check if property exists, even if cache is disabled.')]
    public function testIssetHavingDisabledCache(string $json, object $expected): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file, false);

        // Execute
        foreach ($expected as $i => $value) {

            // Expect
            $this->assertEquals(true, isset($instance->$i));
        }
        $this->assertEquals(false, isset($instance->invalid_position));
    }

    #[TestDox('loading a JSON with an object expects to be able to check if property exists using cache.')]
    public function testIssetCache(): void
    {
        // Prepare
        $json = '{"x": 1, "y": 2, "z": 3}';
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Access the first element: it will be possible to check if element "x" exists
        $foo = $instance->x;
        $this->assertEquals(true, isset($instance->x));

        // Checking position "y": the cursor will advance until it finds element "y", but it will not read the entire object
        $this->assertEquals(true, isset($instance->y));

        // After traversing the object: all elements can be checked from cache
        foreach ($instance as $value) {
            // itnore
        }

        $this->assertEquals(true, isset($instance->z));
    }

    #[DataProvider('nonEmptyJsonObjectWithValuesProvider')]
    #[TestDox('loading a JSON with an object expects to be able to use magic get.')]
    public function testMagicGet(string $json, object $expected): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Execute
        foreach ($expected as $i => $value) {

            // Expect
            $this->assertEquals($value, $instance->$i->getDecodedValue());
        }

        $this->assertEquals(null, $instance->invalid_position);
    }

    #[DataProvider('nonEmptyJsonObjectWithValuesProvider')]
    #[TestDox('loading a JSON with an object expects to be able to use magic get, even if cache is disabled.')]
    public function testMagicGetHavingDisabledCache(string $json, object $expected): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file, false);

        // Execute
        foreach ($expected as $i => $value) {

            // Expect
            $this->assertEquals($value, $instance->$i->getDecodedValue());
        }
        $this->assertEquals(null, $instance->invalid_position);
    }

    #[TestDox('loading a JSON with an object expects to be able to use magic get using cache.')]
    public function testMagicGetCache(): void
    {
        // Prepare
        $json = '{"x": 1, "y": 2, "z": 3}';
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Access the first element: it will be possible to check if element "x" exists
        $foo = $instance->x;
        $this->assertEquals(1, $instance->x->getDecodedValue());

        // Checking position "y": the cursor will advance until it finds element "y", but it will not read the entire object
        $this->assertEquals(2, $instance->y->getDecodedValue());

        // After traversing the object: all elements can be checked from cache
        foreach ($instance as $value) {
            // itnore
        }

        $this->assertEquals(3, $instance->z->getDecodedValue());
    }

    #[TestDox('loading a JSON with an object expects to throw an exeption if trying to set a property.')]
    public function testMagicSet(): void
    {
        // Prepare
        $json = '{}';
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Expect
        $this->expectException(LogicException::class);

        // Execute
        $instance->z = true;
    }

    #[TestDox('loading a JSON with an object expects to throw an exeption if trying to unset a property.')]
    public function testMagicUnset(): void
    {
        // Prepare
        $json = '{"x":1}';
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Expect
        $this->expectException(LogicException::class);

        // Execute
        unset($instance->x);
    }

    #[TestDox('loading a JSON with an object expects to throw an exeption if trying to set an element to an offset.')]
    public function testOffsetSet(): void
    {
        // Prepare
        $json = '{}';
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Expect
        $this->expectException(LogicException::class);

        // Execute
        $instance['z'] = true;
    }

    #[TestDox('loading a JSON with an object expects to throw an exeption if trying to unset an element of an offset.')]
    public function testOffsetUnset(): void
    {
        // Prepare
        $json = '{"x": 1, "y": 2}';
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Expect
        $this->expectException(LogicException::class);

        // Execute
        unset($instance['x']);
    }

    #[DataProvider('jsonObjectWithDecodedValuesProvider')]
    #[TestDox('loading a JSON with an object expects to check the count correctly.')]
    public function testCount(string $json, object $expected): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Execute
        $count = count($instance);

        // Expect
        $this->assertEquals(count((array) $expected), $count);
    }

    #[DataProvider('jsonObjectWithDecodedValuesProvider')]
    #[TestDox('loading a JSON with an object expects to check the count twice correctly.')]
    public function testCountTwice(string $json, object $expected): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file);

        // Execute
        $count1 = count($instance);
        $count2 = count($instance);

        // Expect
        $this->assertEquals(count((array) $expected), $count1);
        $this->assertEquals(count((array) $expected), $count2);
    }

    #[DataProvider('jsonObjectWithDecodedValuesProvider')]
    #[TestDox('loading a JSON with an object expects to check the count twice correctly, even if cache is disabled.')]
    public function testCountTwiceHavingDisabledCache(string $json, object $expected): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file, false);

        // Execute
        $count1 = count($instance);
        $count2 = count($instance);

        // Expect
        $this->assertEquals(count((array) $expected), $count1);
        $this->assertEquals(count((array) $expected), $count2);
    }

    #[DataProvider('jsonObjectWithDecodedValuesProvider')]
    #[TestDox('loading a JSON with an object expects to be JSON serializable.')]
    public function testJsonSerializable(string $json, object $expected): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file, false);

        // Execute
        $result = json_encode($instance);

        // Expect
        $this->assertEquals(json_encode($expected), $result);
    }

    #[DataProvider('invalidJsonObjectProvider')]
    #[TestDox('loading a JSON with an invalid object expects to throw an exception.')]
    public function testInvalidJsonObject(string $json): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);
        $instance = JsonElement::load($file, false);

        // Expect
        $this->expectException(UnexpectedValueException::class);

        // Execute
        $instance->getDecodedValue();
    }

    #[TestDox('loading a JSON with an array with 2 objects expects to parse the first element to get the second.')]
    public function testReadCurrentJsonElement(): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile('[{"x":1},{"x":2}]');
        $instance = JsonElement::load($file, false);

        // Execute
        $value = $instance[1]->getDecodedValue();

        // Expect
        $this->assertEquals((object) ['x' => 2], $value);
    }

    #[Group('memory')]
    #[TestDox('loading a very big JSON object, it should not increase memory usage more than 1Kb.')]
    public function testMemoryUsage(): void
    {
        // Prepare
        $fileContent = (function () {
            yield '{';
            $comma = '';
            for ($i = 1; $i <= 10000; $i++) {
                yield sprintf('%s"key%s": "%s"', $comma, $i, md5($i));
                $comma = ',';
            }
            yield '}';
        })();
        $file = TempFileHelper::createTempFile($fileContent);

        // Execute
        $initialMemory = memory_get_usage(true);
        $instance = JsonElement::load($file, false);
        $counter = 0;
        foreach ($instance as $prop => $element) {
            $counter += 1;
        }
        $finalMemory = memory_get_usage(true);
        $totalMemoryUsage = $finalMemory - $initialMemory;

        $lastProp = $prop;
        $lastElement = $element->getDecodedValue();

        // Expect
        $this->assertEquals(10000, $counter);
        $this->assertEquals('key10000', $lastProp);
        $this->assertEquals(md5('10000'), $lastElement);
        $this->assertLessThan(1024, $totalMemoryUsage); // Less than 1Kb
    }
}