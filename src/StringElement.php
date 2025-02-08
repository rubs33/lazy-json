<?php

declare(strict_types=1);

namespace LazyJson;

use Exception;
use IteratorAggregate;
use RuntimeException;
use Traversable;
use UnexpectedValueException;

use function chr;
use function ctype_cntrl;
use function ctype_xdigit;
use function hexdec;
use function json_decode;
use function ord;
use function sprintf;
use function substr;

use const JSON_THROW_ON_ERROR;

/**
 * Wrapper class that represents a string of a JSON
 *
 * @implements IteratorAggregate<string>
 */
class StringElement extends JsonElement implements IteratorAggregate
{
    /**
     * Decode a UTF-16 symbol with 1 or 2 hex codes of 4 digits
     *
     * @param $hexCode The first hex code (with 4 digits)
     * @param $secondHexCode The second hex code (with 4 digits)
     * @return string The decoded unicode char in UTF-8 encoding
     * @throws UnexpectedValueException If it fails to decode the UTF-16 symbol
     */
    private static function decodeUtf16(string $hexCode, ?string $secondHexCode = null): string
    {
        if ($secondHexCode === null) {
            $jsonString = sprintf('"\\u%s"', $hexCode);
        } else {
            $jsonString = sprintf('"\\u%s\\u%s"', $hexCode, $secondHexCode);
        }

        try {
            $value = json_decode($jsonString, null, 1, JSON_THROW_ON_ERROR);

            //  It should never catch an exception if json_decode works as expected
            // @codeCoverageIgnoreStart
            if (!is_string($value)) {
                throw new UnexpectedValueException(sprintf(
                    'Unexpected type of a unicode string decode process: %s (expected "string").',
                    gettype($value),
                ));
            }
            // @codeCoverageIgnoreEnd
            return $value;
        } catch (Exception $e) {
            throw new UnexpectedValueException(
                sprintf('Failed to decode unicode sequence: %s.', $jsonString),
                0,
                $e,
            );
        }
    }

    /**
     * Magic method to return the object as a string, when requested
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->getDecodedValue();
    }

    /**
     * {@inheritdoc}
     *
     * @param bool $associative Whether the decoded JSON objects will be represented by associative arrays
     * (default false)
     * @return string
     */
    public function getDecodedValue(bool $associative = false): string
    {
        $buffer = '';
        foreach ($this->getIterator() as $char) {
            $buffer .= $char;
        }
        return $buffer;
    }

    /**
     * Gets the decoded value of the JSON string with an iterator of characters,
     * then it does not need to store the entire string in memory.
     * (from IteratorAggregate interface)
     *
     * @return Traversable<string> An iterable object with strings (a single unicode char by each iteration)
     * @throws RuntimeException If the file cannot be read
     * @throws UnexpectedValueException If the string is not well-formed in the file
     */
    public function getIterator(): Traversable
    {
        $currentPosition = $this->startPosition;
        $this->setFilePosition($this->startPosition);

        // Read " (start of a JSON string)
        $char = $this->readBytes(1);
        assert(
            $char === '"',
            new UnexpectedValueException(sprintf(
                'Invalid JSON string. Expected double-quote at position %d.',
                $currentPosition,
            )),
        );
        $currentPosition += 1;

        $endOfString = false;
        while (!$endOfString) {
            $this->setFilePosition($currentPosition);
            $char = $this->readBytes(1);
            if (ctype_cntrl($char)) {
                throw new UnexpectedValueException(sprintf(
                    'Invalid JSON string. Control character at position %d (ord=%d).',
                    $currentPosition,
                    ord($char),
                ));
            }
            $currentPosition += 1;

            if ($char === '"') {
                $endOfString = true;
                continue;
            }
            if ($char === '\\') {
                $escapedChar = $this->readEscapedChar();
                $currentPosition = $this->getCurrentFilePosition();
                yield $escapedChar;
                continue;
            }

            yield $char;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function readCurrentJsonElement(): void
    {
        // Iterate over the chars to read the entire string
        // Then the file cursor will be placed in the next valid byte after the string
        foreach ($this->getIterator() as $char) {
            // Ignore $char
        }
    }

    /**
     * Reads an escaped char from the file handler, advances the file cursor to the next valid position
     * and returns the decoded char
     *
     * @return string The decoded escaped char in UTF-8 encoding
     * @throws RuntimeException If the file cannot be read
     * @throws UnexpectedValueException If the file does not have a valid escaped char at current position
     */
    private function readEscapedChar(): string
    {
        $currentPosition = $this->getCurrentFilePosition();
        $char = $this->readBytes(1);

        switch ($char) {
            case '"':
                return '"';
            case '\\':
                return '\\';
            case '/':
                return '/';
            case 'b':
                return chr(8); // back-space
            case 'f':
                return "\f";
            case 'n':
                return "\n";
            case 'r':
                return "\r";
            case 't':
                return "\t";
            case 'u':
                return $this->readUnicodeChar();
            default:
                throw new UnexpectedValueException(sprintf(
                    'Invalid JSON string. Invalid escaped sequence at position %d: escaped sequence = "%s".',
                    $currentPosition,
                    $char,
                ));
        }
    }

    /**
     * Reads an unicode char (after "\u"), advances the cursor to the next valid position
     * and returns the decoded string
     *
     * @return string The decoded unicode char in UTF-8 encoding
     * @throws UnexpectedValueException If the file does not have a valid unicode char at current position of the file
     */
    private function readUnicodeChar(): string
    {
        $initialPosition = $this->getCurrentFilePosition();

        $utf16HexCode = $this->readBytes(4);
        if (!ctype_xdigit($utf16HexCode)) {
            throw new UnexpectedValueException(sprintf(
                'Invalid JSON string.'
                . 'Invalid unicode sequence at position %d: unicode sequence = "%s" (expected 4 hex digits).',
                $initialPosition,
                $utf16HexCode,
            ));
        }

        $utf16DecCode = hexdec($utf16HexCode);

        // UTF-16 symbol represented by 2 bytes
        if ($utf16DecCode < 0xD800) {
            try {
                return self::decodeUtf16($utf16HexCode);

            //  It should never catch an exception if json_decode works as expected
            // @codeCoverageIgnoreStart
            } catch (Exception $e) {
                throw new UnexpectedValueException(
                    sprintf(
                        'Invalid JSON string. '
                        . 'Failed to decode unicode sequence at position %d: unicode sequence = "%s".',
                        $initialPosition,
                        $utf16HexCode,
                    ),
                    0,
                    $e,
                );
            }
            // @codeCoverageIgnoreEnd
        }
        $currentPosition = $initialPosition + 4;

        // UTF-16 symbol represented by 4 bytes (needs to read next UTF-16 sequence)
        $secondUtf16 = $this->readBytes(6);
        if (substr($secondUtf16, 0, 2) !== '\\u') {
            throw new UnexpectedValueException(sprintf(
                'Invalid JSON string. Expected an unicode sequence at position %d.',
                $currentPosition,
            ));
        }
        $secondUtf16HexCode = substr($secondUtf16, 2, 4);
        if (!ctype_xdigit($secondUtf16HexCode)) {
            throw new UnexpectedValueException(sprintf(
                'Invalid JSON string. '
                . 'Invalid unicode sequence at position %d: unicode sequence = "%s" (expected 4 hex digits).',
                $currentPosition,
                $secondUtf16HexCode,
            ));
        }

        try {
            return self::decodeUtf16($utf16HexCode, $secondUtf16HexCode);
        } catch (Exception $e) {
            throw new UnexpectedValueException(
                sprintf(
                    'Invalid JSON string. '
                    . 'Failed to decode unicode sequence at position %d: unicode sequence = "%s %s".',
                    $initialPosition,
                    $utf16HexCode,
                    $secondUtf16HexCode,
                ),
                0,
                $e,
            );
        }
    }
}
