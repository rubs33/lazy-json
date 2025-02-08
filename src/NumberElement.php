<?php

declare(strict_types=1);

namespace LazyJson;

use Exception;
use LogicException;
use UnexpectedValueException;

use function is_float;
use function is_infinite;
use function is_int;
use function ctype_digit;
use function json_decode;
use function sprintf;
use function var_export;

use const SEEK_CUR;
use const JSON_THROW_ON_ERROR;
use const JSON_PRESERVE_ZERO_FRACTION;

/**
 * Wrapper class that represents a number of a JSON
 */
class NumberElement extends JsonElement
{
    /**
     * The numeric value of the JSON number
     * @var int|float|null
     */
    protected $value = null;

    /**
     * The raw value read from the JSON file
     * @var string
     */
    protected string $rawValue;

    /**
     * Decode a JSON-encoded number into a PHP int or float
     *
     * @param string $number JSON-encoded number
     * @return int|float
     * @throws LogicException If it failed to decode the number
     */
    private static function decodeNumber(string $number)
    {
        try {
            $value = json_decode($number, null, 1, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
            assert(
                is_int($value) || is_float($value),
                new UnexpectedValueException(sprintf(
                    'Unexpected value while decoding a JSON number: %s.',
                    gettype($value),
                )),
            );
            return $value;

        // It should never catch an exception if the logic of self::readCurrentJsonElement is correct
        // @codeCoverageIgnoreStart
        } catch (Exception $e) {
            throw new LogicException(sprintf('Failed to decode number: %s.', $number), 0, $e);
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * Magic method to return the object as a string, when requested
     *
     * @return string
     */
    public function __toString(): string
    {
        return var_export($this->getDecodedValue(), true);
    }

    /**
     * {@inheritdoc}
     *
     * @param bool $associative Whether the decoded JSON objects will be represented by associative arrays
     * (default false)
     * @return int|float
     */
    public function getDecodedValue(bool $associative = false)
    {
        if ($this->value === null) {
            $this->parse();
        }
        // @codeCoverageIgnoreStart
        if ($this->value === null) {
            throw new \UnexpectedValueException('The value should not be null');
        }
        // @codeCoverageIgnoreEnd
        return $this->value;
    }

    /**
     * Get the original value read from the JSON File
     *
     * @return string
     */
    public function getRawValue(): string
    {
        $this->parse();
        return $this->rawValue;
    }

    /**
     * {@inheritdoc}
     */
    protected function readCurrentJsonElement(): void
    {
        $buffer = '';

        $currentPosition = $this->startPosition;
        $this->setFilePosition($this->startPosition);

        // Sign (optional)
        $char = $this->checkCurrentByte(false);
        if ($char === '-') {
            $this->readBytes(1);
            $currentPosition += 1;
            $buffer .= $char;
        }

        // Integer part (required)
        $char = $this->readBytes(1);
        if ($char === '0') {
            $currentPosition += 1;
            $buffer .= $char;
        } elseif (ctype_digit($char)) {
            $currentPosition += 1;
            $buffer .= $char;

            $char = $this->readBytes(1, false);
            while (ctype_digit($char)) {
                $currentPosition += 1;
                $buffer .= $char;

                $char = $this->readBytes(1, false);
            }
            if ($char === '' && $this->fileHandler->eof()) {
                $this->setRawValue($buffer);
                return;
            }
            $this->setFilePosition(-1, SEEK_CUR);
        } else {
            throw new UnexpectedValueException(sprintf(
                'Invalid JSON. Unexpected char for a number at position %d.',
                $currentPosition,
            ));
        }

        // Fraction (optional)
        $char = $this->checkCurrentByte(false);
        if ($char === '.') {
            $this->readBytes(1);
            $currentPosition += 1;
            $buffer .= $char;

            $char = $this->readBytes(1);
            if (ctype_digit($char)) {
                $currentPosition += 1;
                $buffer .= $char;
            } else {
                throw new UnexpectedValueException(sprintf(
                    'Invalid JSON. Unexpected char for a number at position %d.',
                    $currentPosition,
                ));
            }

            $char = $this->readBytes(1, false);
            while (ctype_digit($char)) {
                $currentPosition += 1;
                $buffer .= $char;
                $char = $this->readBytes(1, false);
            }
            if ($char === '' && $this->fileHandler->eof()) {
                $this->setRawValue($buffer);
                return;
            }
            $this->setFilePosition(-1, SEEK_CUR);
        }

        // Expoent (optional)
        $char = $this->checkCurrentByte(false);
        if ($char === 'e' || $char === 'E') {
            $this->readBytes(1);
            $currentPosition += 1;
            $buffer .= $char;

            // Sign (optional, but it is expected a char after "e")
            $char = $this->checkCurrentByte();
            if ($char === '-' || $char === '+') {
                $this->readBytes(1);
                $currentPosition += 1;
                $buffer .= $char;
            }

            $char = $this->readBytes(1);
            if (ctype_digit($char)) {
                $currentPosition += 1;
                $buffer .= $char;
            } else {
                throw new UnexpectedValueException(sprintf(
                    'Invalid JSON. Unexpected char for a number at position %d.',
                    $currentPosition,
                ));
            }

            $char = $this->readBytes(1, false);
            while (ctype_digit($char)) {
                $currentPosition += 1;
                $buffer .= $char;
                $char = $this->readBytes(1, false);
            }
            if ($char === '' && $this->fileHandler->eof()) {
                $this->setRawValue($buffer);
                return;
            }
            $this->setFilePosition(-1, SEEK_CUR);
        }

        $this->setRawValue($buffer);
    }

    /**
     * Save the real value of the number
     *
     * @param int|float $value The real value of the current JSON element
     * @return void
     */
    protected function setValue($value): void
    {
        if (!isset($this->value)) {
            $this->value = $value;
        }
    }

    /**
     * Save the raw value of the number
     *
     * @param string $rawValue The raw value of the current JSON element
     * @return void
     */
    protected function setRawValue(string $rawValue): void
    {
        if (!isset($this->rawValue)) {
            $this->rawValue = $rawValue;
        }
        $this->setValue(self::decodeNumber($rawValue));
    }
}
