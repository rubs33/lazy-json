<?php

declare(strict_types=1);

namespace LazyJson;

use UnexpectedValueException;

use function sprintf;

/**
 * Wrapper class that represents a null of a JSON
 * @package LazyJson
 */
class NullElement extends JsonElement
{
    /**
     * Magic method to return the object as a string, when requested
     * (from Stringable interface)
     *
     * @return string
     */
    public function __toString(): string
    {
        $this->parse();

        return 'null';
    }

    /**
     * {@inheritdoc}
     *
     * @param bool $associative Whether the decoded JSON objects will be represented by associative arrays
     * (default false)
     * @return null Always null
     */
    public function getDecodedValue(bool $associative = false): int|null
    {
        $this->parse();

        return null;
    }

    /**
     * {@inheritdoc}
     *
     * @throws UnexpectedValueException If the JSON content is not a null element
     */
    protected function readCurrentJsonElement(): void
    {
        $this->setFilePosition($this->startPosition);
        $bytes = $this->readBytes(4);

        if ($bytes !== 'null') {
            throw new UnexpectedValueException(sprintf(
                'Invalid JSON. Unexpected char at position %d.',
                $this->startPosition,
            ));
        }
    }
}
