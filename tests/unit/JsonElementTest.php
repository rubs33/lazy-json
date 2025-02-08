<?php

namespace LazyJson\Tests\Unit;

use InvalidArgumentException;
use LazyJson\{
    ArrayElement,
    BooleanElement,
    NullElement,
    NumberElement,
    JsonElement,
    ObjectElement,
    StringElement,
};
use LazyJson\Tests\Unit\Fixtures\TempFileHelper;
use PHPUnit\Framework\TestCase;
use SplFileObject;
use UnexpectedValueException;

/**
 * @testdox \LazyJson\JsonElement
 * @covers \LazyJson\JsonElement
 * @uses \LazyJson\ArrayElement
 * @uses \LazyJson\BooleanElement
 * @uses \LazyJson\NullElement
 * @uses \LazyJson\NumberElement
 * @uses \LazyJson\ObjectElement
 * @uses \LazyJson\StringElement
 */
class JsonElementTest extends TestCase
{
    // Static methods

    public static function jsonProvider(): iterable
    {
        yield 'bool true' => [
            'json' => 'true',
            'expected' => BooleanElement::class,
        ];
        yield 'bool false' => [
            'json' => 'false',
            'expected' => BooleanElement::class,
        ];
        yield 'bool with extra spaces' => [
            'json' => " \r\n\tfalse \r\n\t",
            'expected' => BooleanElement::class,
        ];
        yield 'null' => [
            'json' => 'null',
            'expected' => NullElement::class,
        ];
        yield 'null with extra spaces' => [
            'json' => " \r\n\tnull \r\n\t",
            'expected' => NullElement::class,
        ];
        yield 'number int' => [
            'json' => '123',
            'expected' => NumberElement::class,
        ];
        yield 'number float' => [
            'json' => '123.456',
            'expected' => NumberElement::class,
        ];
        yield 'number with extra spaces' => [
            'json' => " \r\n\t123 \r\n\t",
            'expected' => NumberElement::class,
        ];
        yield 'array' => [
            'json' => '[]',
            'expected' => ArrayElement::class,
        ];
        yield 'array with extra spaces' => [
            'json' => " \r\n\t[ \r\n\t] \r\n\t",
            'expected' => ArrayElement::class,
        ];
        yield 'object' => [
            'json' => '{}',
            'expected' => ObjectElement::class,
        ];
        yield 'object with extra spaces' => [
            'json' => " \r\n\t{ \r\n\t} \r\n\t",
            'expected' => ObjectElement::class,
        ];
        yield 'string' => [
            'json' => '""',
            'expected' => StringElement::class,
        ];
        yield 'string with extra spaces' => [
            'json' => " \r\n\t\"\" \r\n\t",
            'expected' => StringElement::class,
        ];
    }

    public static function invalidJsonProvider(): iterable
    {
        yield 'empty file' => [
            'file' => tempFileHelper::createTempFile(''),
            'expected' => InvalidArgumentException::class,
        ];
        yield 'file containing only whitespaces' => [
            'file' => tempFileHelper::createTempFile(" \r\n\t"),
            'expected' => UnexpectedValueException::class,
        ];
        yield 'invalid content 1' => [
            'file' => tempFileHelper::createTempFile('b'),
            'expected' => UnexpectedValueException::class,
        ];
        yield 'invalid content 2' => [
            'file' => tempFileHelper::createTempFile(chr(0)),
            'expected' => UnexpectedValueException::class,
        ];

        yield 'file not opened for read' => [
            'file' => tempFileHelper::createTempFile('{}', 'w'),
            'expected' => InvalidArgumentException::class,
        ];

        // If executing with non-root user:
        $file = tempFileHelper::createTempFile('{}');
        chmod($file->getRealPath(), 0);
        if (!$file->isReadable()) {
            yield 'file not readable' => [
                'file' => $file,
                'expected' => InvalidArgumentException::class,
            ];
        }
    }

    // Tests

    /**
     * @dataProvider jsonProvider
     * @testdox loading a JSON with the value "$json" must return an instance of $expected.
     */
    public function testInstance(string $json, string $expected): void
    {
        // Prepare
        $file = TempFileHelper::createTempFile($json);

        // Execute
        $instance = JsonElement::load($file);

        // Expect
        $this->assertInstanceOf($expected, $instance);
        $this->assertInstanceOf(JsonElement::class, $instance);
    }

    /**
     * @dataProvider invalidJsonProvider
     * @testdox loading an invalid JSON file must throw an exception.
     */
    public function testInvalidInstance(SplFileObject $file, string $expected): void
    {
        // Expect
        $this->expectException($expected);

        // Execute
        $instance = JsonElement::load($file);
    }

    public function getGenericObject(string $content): JsonElement
    {
        $file = TempFileHelper::createTempFile($content);

        return new class ($file, true) extends JsonElement {
            public function __construct(SplFileObject $file, bool $useCache = true)
            {
                parent::__construct($file, $useCache);
            }

            public function __toString(): string
            {
                return $this->getDecodedValue();
            }

            public function getDecodedValue(bool $associative = false): string
            {
                if ($this->useCache() && $this->isLoaded()) {
                    return 'cached test';
                }

                $this->parse();
                $this->parse();

                return 'test';
            }

            protected function readCurrentJsonElement(): void
            {
                $this->setFilePosition($this->startPosition);
                $this->readWhitespace();
                if ($this->checkCurrentByte() !== 't') {
                    throw new UnexpectedValueException();
                }
                $bytes = $this->readBytes(4);
                if ($bytes !== 'test') {
                    throw new UnexpectedValueException();
                }
            }
        };
    }

    /**
     * @testdox Loading a generic element must work jsonSerializable
     */
    public function testJsonSerialize(): void
    {
        // Prepare
        $obj = $this->getGenericObject(" \r\n\ttest \r\n\t");

        // Execute
        $result1 = json_encode($obj);
        $result2 = json_encode($obj);

        // Expect
        $this->assertEquals('"test"', $result1);
        $this->assertEquals('"cached test"', $result2);
    }

    /**
     * @testdox Loading a generic element must work getDecodedValue
     */
    public function testGetDecodedValue(): void
    {
        // Prepare
        $obj = $this->getGenericObject('test');

        // Execute
        $result1 = $obj->getDecodedValue();
        $result2 = $obj->getDecodedValue();

        // Expect
        $this->assertEquals('test', $result1);
        $this->assertEquals('cached test', $result2);
    }

    /**
     * @testdox Loading a generic element with invalid file must throw an Exception
     */
    public function testGetDecodedValueWithInvalidFileContent(): void
    {
        // Prepare
        $obj = $this->getGenericObject('tes');

        // Expect
        $this->expectException(UnexpectedValueException::class);

        // Execute
        $obj->getDecodedValue();
    }
}
