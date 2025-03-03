<?php

declare(strict_types=1);

namespace LazyJson;

use UnexpectedValueException;

use function sprintf;

/**
 * Wrapper class that represents a boolean of a JSON
 * @package LazyJson
 */
class BooleanElement extends JsonElement
{
    /**
     * The boolean value
     * @var bool
     */
    protected bool $value;

    /**
     * Magic method to return the object as a string, when requested
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->getDecodedValue() ? 'true' : 'false';
    }

    /**
     * {@inheritdoc}
     *
     * @param bool $associative Whether the decoded JSON objects will be represented by associative arrays
     * (default false)
     * @return bool
     */
    public function getDecodedValue(bool $associative = false): bool
    {
        if (!isset($this->value)) {
            $this->parse();
        }
        return $this->value;
    }

    /**
     * {@inheritdoc}
     *
     * @throws UnexpectedValueException If the JSON content is not a valid Boolean value
     */
    protected function readCurrentJsonElement(): void
    {
        $buffer = '';

        $this->setFilePosition($this->startPosition);
        $char = $this->readBytes(1);
        $buffer .= $char;

        if ($char === 't') {
            $buffer .= $this->readBytes(3);
            if ($buffer !== 'true') {
                throw new UnexpectedValueException(sprintf(
                    'Invalid JSON. Unexpected char at position %d.',
                    $this->startPosition,
                ));
            }
            $this->setValue(true);
        } elseif ($char === 'f') {
            $buffer .= $this->readBytes(4);
            if ($buffer !== 'false') {
                throw new UnexpectedValueException(sprintf(
                    'Invalid JSON. Unexpected char at position %d.',
                    $this->startPosition,
                ));
            }
            $this->setValue(false);

        // @codeCoverageIgnoreStart
        } else {
            throw new UnexpectedValueException(sprintf(
                'Invalid JSON. Unexpected value at position %d.',
                $this->startPosition,
            ));
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * Save the value of the current boolean element
     *
     * @param bool $value The value to be saved
     * @return void
     */
    private function setValue(bool $value): void
    {
        if (!isset($this->value)) {
            $this->value = $value;
        }
    }
}
