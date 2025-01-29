<?php

declare(strict_types=1);

namespace LazyJson;

use ArrayAccess;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use LogicException;
use Traversable;
use UnexpectedValueException;

use function array_key_exists;
use function is_int;
use function sprintf;

/**
 * Wrapper class that represents an array of a JSON
 *
 * @implements ArrayAccess<int<0,max>,JsonElement>
 * @implements IteratorAggregate<JsonElement>
 */
class ArrayElement extends JsonElement implements ArrayAccess, Countable, IteratorAggregate
{
    // PROPERTIES

    /**
     * Position (byte) of the array elements in the JSON file
     * (used only if cache is activated)
     *
     * @var array<int<0,max>,int<0,max>>
     */
    protected array $elementPositions = [];

    /**
     * Detected number of elements in the array.
     * Note: it is only filled after the array is traversed for the first time using the iterator.
     *
     * @var int<0,max>
     */
    protected readonly int $totalElements;

    // CONCRETE METHODS

    /**
     * {@inheritdoc}
     *
     * @param bool $associative Whether the decoded JSON objects will be represented by associative arrays
     * (default false)
     * @return mixed[] The decoded version of the current array
     * @throws UnexpectedValueException If the JSON is not valid
     */
    public function getDecodedValue(bool $associative = false): array
    {
        $arr = [];
        foreach ($this->getIterator() as $jsonElement) {
            $arr[] = $jsonElement->getDecodedValue($associative);
        }
        return $arr;
    }

    /**
     * Magic method to return the object as a string, when requested
     * (from Stringable interface)
     *
     * @return string
     */
    public function __toString(): string
    {
        return 'Array';
    }

    /**
     * Count the elements of the current array
     * (from Countable interface)
     *
     * @return int<0,max> The number of elements in the array
     * @throws UnexpectedValueException If the JSON is not valid
     */
    public function count(): int
    {
        if (!isset($this->totalElements)) {
            $this->parse();
        }

        return $this->totalElements;
    }

    /**
     * Return an iterator of elements of the array wrapped by JsonElement classes
     * (from IteratorAggregate interface)
     *
     * @return Traversable<int<0,max>,JsonElement> An iterator that contains elements of type LazyJson\JsonElement
     * @throws UnexpectedValueException If the JSON is not valid
     */
    public function getIterator(): Traversable
    {
        if ($this->useCache() && isset($this->totalElements)) {
            yield from $this->getIteratorFromCache();
            return;
        }

        /** @var int<0,max> */
        $currentIndex = 0;

        $this->setFilePosition($this->startPosition);

        // Read "["
        $char = $this->readBytes(1);
        assert(
            $char === '[',
            new UnexpectedValueException(sprintf(
                'Invalid JSON array. Unexpected char at position %d.',
                $this->getCurrentFilePosition() - 1,
            )),
        );
        $this->readWhitespace();

        // Read array content
        $char = $this->checkCurrentByte();
        while ($char !== ']') {
            $currentPosition = $this->getCurrentFilePosition();
            if ($this->useCache()) {
                $this->elementPositions[$currentIndex] = $currentPosition;
            }

            $childElement = JsonElement::load($this->fileHandler, $this->useCache());

            yield $currentIndex => $childElement;

            $currentIndex += 1;

            $this->setFilePosition($currentPosition);
            $childElement->parse();
            $this->readWhitespace();

            $currentPosition = $this->getCurrentFilePosition();
            $char = $this->checkCurrentByte();
            if ($char === ',') {
                $this->readBytes(1);
                $this->readWhitespace();
                $char = $this->checkCurrentByte();
                if ($char === ']') {
                    throw new UnexpectedValueException(sprintf(
                        'Invalid JSON array. Unexpected end of array at position %d.")',
                        $currentPosition
                    ));
                }
            } elseif ($char === ']') {
                continue;
            } else {
                throw new UnexpectedValueException(sprintf(
                    'Invalid JSON array. Unexpected value at position %d (expected "," or "]", received "%s")',
                    $currentPosition,
                    $this->readBytes(10),
                ));
            }
        }

        // Read "]"
        $char = $this->readBytes(1);
        assert(
            $char === ']',
            new UnexpectedValueException(sprintf(
                'Invalid JSON array. Unexpected value at position %d (expected "]", received "%s").',
                $this->getCurrentFilePosition() - 1,
                $this->readBytes(10),
            )),
        );

        $this->setTotalElements($currentIndex);
    }

    /**
     * Checks whether the offset exits in the array
     * (from ArrayAccess interface)
     *
     * @param mixed $offset The offset to be checked
     * @return bool Whether the offset exists in the array
     */
    public function offsetExists(mixed $offset): bool
    {
        if (!is_int($offset)) {
            return false;
        }

        // Check from cache
        if (isset($this->totalElements)) {
            return $offset >= 0 && $offset < $this->totalElements;
        }
        if ($this->useCache() && array_key_exists($offset, $this->elementPositions)) {
            return true;
        }

        // Check manually
        foreach ($this->getIterator() as $key => $value) {
            if ($key === $offset) {
                return true;
            }
        }
        return false;
    }

    /**
     * Fetch an element of the array by its offset
     * (from ArrayAccess interface)
     *
     * @param mixed $offset The offset of the element to be fetched
     * @return ?JsonElement The value of the element (wrapped by LazyJson\JsonElement) or null if it does not exist
     */
    public function offsetGet(mixed $offset): ?JsonElement
    {
        if (!is_int($offset)) {
            return null;
        }

        if ($this->useCache() && isset($this->elementPositions[$offset])) {
            $this->setFilePosition($this->elementPositions[$offset]);
            return JsonElement::load($this->fileHandler, $this->useCache());
        }
        foreach ($this->getIterator() as $key => $value) {
            if ($key === $offset) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Throws an exception, since this class does not support updates in the array
     * (from ArrayAccess interface)
     *
     * @throws LogicException Always throws LogicException
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('ArrayElement does not allow to set elements dynamically.');
    }

    /**
     * Throws an exception, since this class does not support updates in the array
     * (from ArrayAccess interface)
     *
     * @throws LogicException Always throws LogicException
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('ArrayElement does not allow to unset elements dynamically.');
    }

    /**
     * {@inheritdoc}
     */
    protected function readCurrentJsonElement(): void
    {
        // Traversing the iterator to read the entire array
        // Then the file cursor will be placed in the next valid byte after the array
        foreach ($this->getIterator() as $element) {
            // Ignore $element
        }
    }

    /**
     * Return an iterator from the cache
     *
     * @return Traversable<int<0,max>,JsonElement> An iterator that contains elements of type LazyJson\JsonElement
     * @throws UnexpectedValueException If the JSON is not valid
     */
    protected function getIteratorFromCache(): Traversable
    {
        $this->setFilePosition($this->startPosition);

        // Read "["
        $char = $this->readBytes(1);
        assert(
            $char === '[',
            new UnexpectedValueException(sprintf(
                'Invalid JSON array. Unexpected value for array at position %d.',
                $this->getCurrentFilePosition() - 1,
            )),
        );
        $this->readWhitespace();

        $element = null;
        foreach ($this->elementPositions as $index => $position) {
            $this->setFilePosition($position);
            $element = JsonElement::load($this->fileHandler, $this->useCache());
            yield $index => $element;
        }

        // Read the last element to ensure the cursor will advance to the next element
        if ($element instanceof JsonElement) {
            $element->parse();
        }

        $this->readWhitespace();

        // Read the "]" (after the last element of the array)
        $char = $this->readBytes(1);
        assert(
            $char === ']',
            new UnexpectedValueException(sprintf(
                'Invalid JSON array. Unexpected char for array at position %d.',
                $this->getCurrentFilePosition() - 1,
            )),
        );
    }

    /**
     * Save the total number of elements of current array
     *
     * @param int<0,max> $totalElements The value to save
     * @return void
     */
    private function setTotalElements(int $totalElements): void
    {
        if (!isset($this->totalElements)) {
            $this->totalElements = $totalElements;
        }
    }
}
