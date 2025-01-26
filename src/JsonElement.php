<?php

/*
 * MIT License
 *
 * Copyright (c) 2025 Rubens Takiguti Ribeiro <rubs33@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

declare(strict_types=1);

namespace LazyJson;

use InvalidArgumentException;
use JsonSerializable;
use SplFileObject;
use RuntimeException;
use Stringable;
use UnexpectedValueException;

use function ctype_digit;

use const SEEK_CUR;
use const SEEK_SET;

/**
 * Abstract class that represents an element of a JSON
 */
abstract class JsonElement implements JsonSerializable, Stringable
{
    // PROPERTIES

    /**
     * Instance of the SplFileHandler to read the JSON file
     * @var SplFileObject
     */
    protected readonly SplFileObject $fileHandler;

    /**
     * Initial position (byte) of the element in the file
     * @var int<0,max>
     */
    protected readonly int $startPosition;

    /**
     * Final position (byte) of the element in the file
     * @var int<0,max>
     */
    protected readonly int $endPosition;

    /**
     * Whether to use cache to store useful data to improve performance
     * @var bool
     */
    protected readonly bool $useCache;

    // ABSTRACT METHODS

    /**
     * Returns the decoded value of the current JSON element
     *
     * @param bool $associative Whether the decoded JSON objects will be represented by associative arrays
     * (default false)
     * @return array<mixed>|object|string|int|float|bool|null
     */
    abstract public function getDecodedValue(bool $associative = false): array|object|string|int|float|bool|null;

    /**
     * Parse/Read the current element of the JSON file and advance the cursor to the next byte after
     * the current element.
     * It must not populate self::$startPosition or self::$endPosition, because the method
     * self::parse is the responsible for that.
     *
     * @return void
     * @throws RuntimeException If the JSON file is not valid
     */
    abstract protected function readCurrentJsonElement(): void;

    // STATIC METHODS

    /**
     * Main method to load a JSON file and return a JsonElement.
     * Note: it returns a lazzy object. That means the file is not parsed.
     * This method only detects which type of JSON element the cursor of the
     * file handler points to based on the current byte.
     *
     * @param SplFileObject $fileHandler The JSON file
     * @param bool $useCache Whether to use cache to store useful data to improve performance
     * @return JsonElement The JSON element that has a lazzy behavior to improve memory usage
     * @throws InvalidArgumentException If the file handler does not represent a readable non-empty file.
     * @throws RuntimeException If it fails to read the file.
     * @throws RuntimeException If the current position of the file does not point to a valid JSON element.
     */
    final public static function load(SplFileObject $fileHandler, bool $useCache = true): JsonElement
    {
        self::validateFileHandler($fileHandler);

        // Ignore whitespace
        $char = $fileHandler->fread(1);
        while ($char === ' ' || $char === "\n" || $char === "\r" || $char === "\t") {
            $char = $fileHandler->fread(1);
        }
        // @codeCoverageIgnoreStart
        if ($char === false) {
            throw new RuntimeException('Failed to read JSON file.');
        }
        // @codeCoverageIgnoreEnd

        $currentPosition = $fileHandler->ftell();

        // @codeCoverageIgnoreStart
        if ($currentPosition === false) {
            throw new RuntimeException('Failed to detect current cursor position in the JSON file.');
        }
        // @codeCoverageIgnoreEnd

        if ($char === '' && $fileHandler->eof()) {
            throw new UnexpectedValueException(sprintf(
                'Invalid JSON. Unexpected end of file at position %d.',
                $currentPosition,
            ));
        }
        $seekResult = $fileHandler->fseek(-1, SEEK_CUR);

        // @codeCoverageIgnoreStart
        if ($seekResult === -1) {
            throw new RuntimeException(sprintf(
                'Failed to move the file cursor to offset %d, using whence = %d',
                -1,
                SEEK_CUR,
            ));
        }
        // @codeCoverageIgnoreEnd

        $class = self::detectJsonElementTypeByFirstByte($char, $currentPosition);

        return new $class($fileHandler, $useCache);
    }

    /**
     * Validates whether the file handler is open to read a non-empty file
     *
     * @return void
     * @throws RuntimeException If the file handler is not a file, is not readable, or is empty.
     */
    final protected static function validateFileHandler(SplFileObject $fileHandler): void
    {
        // @codeCoverageIgnoreStart
        if (!$fileHandler->isFile()) {
            throw new InvalidArgumentException('File handler does not point to a file.');
        }
        // @codeCoverageIgnoreEnd

        if (!$fileHandler->isReadable()) {
            throw new InvalidArgumentException('File handler is not readable.');
        }
        if ($fileHandler->getSize() === 0) {
            throw new InvalidArgumentException('File handler points to an empty file.');
        }
    }

    /**
     * Detects the JsonElement type by the first byte of the element
     *
     * @param string $char The first byte
     * @param int $currentPosition
     * @return class-string<JsonElement> The name of the class (that extends JsonElement)
     * @throws UnexpectedValueException If the first byte is not a valid beginning of a JSON element
     */
    private static function detectJsonElementTypeByFirstByte(string $char, int $currentPosition): string
    {
        if ($char === '{') {
            return ObjectElement::class;
        }

        if ($char === '[') {
            return ArrayElement::class;
        }

        if ($char === '"') {
            return StringElement::class;
        }

        if ($char === 't' || $char === 'f') {
            return BooleanElement::class;
        }

        if ($char === 'n') {
            return NullElement::class;
        }

        if ($char === '-' || ctype_digit($char)) {
            return NumberElement::class;
        }

        throw new UnexpectedValueException(sprintf(
            'Invalid JSON. Unexpected value at position %d: "%s".',
            $currentPosition,
            $char,
        ));
    }

    // CONCRETE METHODS

    /**
     * Return the serializable value for json_encode
     * (from JsonSerializable interface)
     *
     * @return mixed The value to be serialized
     */
    final public function jsonSerialize(): mixed
    {
        return $this->getDecodedValue();
    }

    /**
     * Parse/Read the element of the JSON file and advance the cursor to the next byte after the current element
     *
     * @return void
     * @throws RuntimeException If the JSON file is not valid
     */
    final public function parse(): void
    {
        if ($this->isLoaded()) {
            $this->setFilePosition($this->endPosition);
            return;
        }
        $this->setFilePosition($this->startPosition);
        $this->readCurrentJsonElement();
        if (!isset($this->endPosition)) {
            $this->endPosition = $this->getCurrentFilePosition();
        }
    }

    /**
     * Returns whether the JSON element was loaded (the startPosition and endPosition were populated)
     *
     * @return bool whether the JSON element was loaded (the startPosition and endPosition were populated)
     */
    final protected function isLoaded(): bool
    {
        return isset($this->endPosition);
    }

    /**
     * Return whether the flag to use cache is activated
     *
     * @return bool Whether the flag to use cache is activated
     */
    final protected function useCache(): bool
    {
        return $this->useCache;
    }

    /**
     * Construct the JSON element with a lazzy approach
     *
     * @param SplFileObject $fileHandler Instance of a SplFileObject to read the JSON file
     * @param bool $useCache Whether to use cache to store useful data to improve performance
     * @throws RuntimeException If it was not possible to detect the current cursor position of the JSON file.
     */
    protected function __construct(SplFileObject $fileHandler, bool $useCache = true)
    {
        $this->fileHandler = $fileHandler;
        $this->useCache = $useCache;
        $this->startPosition = $this->getCurrentFilePosition();
    }

    /**
     * Return the current position of the cursor to the file
     *
     * @return int<0,max> The current position
     * @throws RuntimeException If it was not possible to detect the position
     */
    final protected function getCurrentFilePosition(): int
    {
        $position = $this->fileHandler->ftell();

        // @codeCoverageIgnoreStart
        if ($position === false || $position < 0) {
            throw new RuntimeException('Failed to get current cursor position of file.');
        }
        // @codeCoverageIgnoreEnd

        return $position;
    }

    /**
     * Move the file cursor to the expected position, based on offset and whence
     * Check the method SplFileObject::fseek for more details
     *
     * @param int $offset The offset. A negative value can be used to move backwards through
     * the file which is useful when SEEK_END is used as the whence value.
     * @param int $whence One of the PHP consts:
     *  SEEK_SET - Set position equal to offset bytes. (default value)
     *  SEEK_CUR - Set position to current location plus offset.
     *  SEEK_END - Set position to end-of-file plus offset.
     * @return void
     * @throws RuntimeException If it failed to move the cursor
     */
    final protected function setFilePosition(int $offset, int $whence = SEEK_SET): void
    {
        $result = $this->fileHandler->fseek($offset, $whence);

        // @codeCoverageIgnoreStart
        if ($result === -1) {
            throw new RuntimeException(sprintf(
                'Failed to move the file cursor to offset %d, using whence = %d',
                $offset,
                $whence,
            ));
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * Reads a number of bytes of the file handler and advances the cursor
     *
     * @param $size The number of bytes to read
     * @param $requireSize Whether the method must throw an exception if the read size is lower than expected
     * @return string The read bytes
     * @throws RuntimeException If the file cannot be read
     * @throws UnexpectedValueException If the JSON string is not well-formed
     */
    final protected function readBytes(int $size, bool $requireSize = true): string
    {
        $currentPosition = $this->getCurrentFilePosition();
        $bytes = $this->fileHandler->fread($size);

        // @codeCoverageIgnoreStart
        if ($bytes === false) {
            throw new RuntimeException(sprintf('Failed to read the file at position %d.', $currentPosition));
        }
        // @codeCoverageIgnoreEnd

        if ($requireSize && strlen($bytes) !== $size && $this->fileHandler->eof()) {
            throw new UnexpectedValueException(sprintf(
                'Invalid string. Unexpectedd end of file at position %d.',
                $currentPosition,
            ));
        }

        return $bytes;
    }

    /**
     * Reads a sequence of whitespaces and keeps the file cursor in the next non-whitespace char
     */
    final protected function readWhitespace(): void
    {
        do {
            $char = $this->fileHandler->fread(1);
            $isWhitespace = $char === ' '
                || $char === "\n"
                || $char === "\r"
                || $char === "\t";
        } while ($isWhitespace);
        if (!$this->fileHandler->eof()) {
            $this->setFilePosition(-1, SEEK_CUR);
        }
    }

    /**
     * Checks the byte of the current file cursor, but keeps the cursor at the same position
     *
     * @param bool $required Whether the current byte must exist or not
     * @return string The byte of the current position
     * @throws RuntimeException If it is not possible to read the file
     */
    final protected function checkCurrentByte($required = true): string
    {
        $currentPosition = $this->getCurrentFilePosition();
        $char = $this->fileHandler->fread(1);

        // @codeCoverageIgnoreStart
        if ($required && $char === '' && $this->fileHandler->eof()) {
            throw new UnexpectedValueException(sprintf('Unexpected end of file at position %d.', $currentPosition));
        }
        if ($char === false) {
            throw new RuntimeException(sprintf('Failed to read the file at position %d.', $currentPosition));
        }
        // @codeCoverageIgnoreEnd

        $this->setFilePosition($currentPosition);

        return $char;
    }
}
