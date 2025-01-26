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
use function is_string;

/**
 * Wrapper class that represents an Object of a JSON
 *
 * @implements ArrayAccess<string,JsonElement>
 * @implements IteratorAggregate<string,JsonElement>
 */
class ObjectElement extends JsonElement implements ArrayAccess, Countable, IteratorAggregate
{
    // PROPERTIES

    /**
     * Position (byte) of the object properties in the JSON file
     * (used only if cache is activated)
     *
     * @var array<string,int<0,max>>
     */
    protected array $propertyPositions = [];

    /**
     * Detected number of properties in the object
     *
     * @var int<0,max>
     */
    protected readonly int $totalProperties;

    // CONCRETE METHODS

    /**
     * Get a property on-demand
     *
     * @param string $name The property name
     * @return ?JsonElement The element wrapped by LazyJson\JsonElement or null
     */
    public function __get(string $name): ?JsonElement
    {
        return $this->offsetGet($name);
    }

    /**
     * Checks whether a property exist on-demand
     *
     * @param string $name The property name to be checked
     * @return bool Whether the property exist
     */
    public function __isset(string $name): bool
    {
        return $this->offsetExists($name);
    }

    /**
     * Always throws an exception, since the object is read-only
     *
     * @param string $name The property name to be set
     * @param mixed $value The value to be set
     * @return void
     * @throws LogicException
     */
    public function __set(string $name, mixed $value): void
    {
        throw new LogicException('The object is read-only and cannot overwrite a value');
    }

    /**
     * Always throws an exception, since the object is read-only
     *
     * @param string $name The property name to be unset
     * @return void
     * @throws LogicException
     */
    public function __unset(string $name): void
    {
        throw new LogicException('The object is read-only and cannot unset a value');
    }

    /**
     * Magic method to return the object as a string, when requested
     * (from Stringable interface)
     *
     * @return string
     */
    public function __toString(): string
    {
        return 'Object';
    }

    /**
     * {@inheritdoc}
     *
     * @param bool $associative Whether the decoded JSON objects will be represented by associative arrays
     * (default false)
     * @return object|array<string,mixed> The decoded version of the current object
     * @throws UnexpectedValueException If the JSON is not valid
     */
    public function getDecodedValue(bool $associative = false): object|array
    {
        $data = [];
        foreach ($this->getIterator() as $property => $jsonElement) {
            $data[$property] = $jsonElement->getDecodedValue($associative);
        }
        return $associative ? $data : (object) $data;
    }

    /**
     * Count the properties of the current object
     * (from Countable interface)
     *
     * @return int<0,max> The number of properties in the object
     * @throws UnexpectedValueException If the JSON is not valid
     */
    public function count(): int
    {
        if (!isset($this->totalProperties)) {
            $this->parse();
        }
        return $this->totalProperties;
    }

    /**
     * Return an iterator of properties of the object wrapped by JsonElement classes,
     * but keeping the property name as a regular PHP string (not wrapped)
     * (from IteratorAggregate interface)
     *
     * @return Traversable<string,JsonElement> An iterator indexed by the property name and point to
     * the elements of the type LazyJson\JsonElement
     * @throws UnexpectedValueException If the JSON is not valid
     */
    public function getIterator(): Traversable
    {
        if ($this->useCache() && isset($this->totalProperties)) {
            yield from $this->getIteratorFromCache();
            return;
        }

        $totalProperties = 0;

        $this->setFilePosition($this->startPosition);

        // Read "{"
        $char = $this->readBytes(1);
        assert(
            $char === '[',
            throw new UnexpectedValueException(sprintf(
                'Invalid JSON object. Unexpected char at position %d.',
                $this->getCurrentFilePosition() - 1,
            )),
        );
        $this->readWhitespace();

        // Read object content
        $char = $this->checkCurrentByte();
        while ($char !== '}') {
            // Property name
            $currentPosition = $this->getCurrentFilePosition();
            $propertyElement = JsonElement::load($this->fileHandler);
            if (!($propertyElement instanceof StringElement)) {
                throw new UnexpectedValueException(sprintf(
                    'Invalid JSON object. Unexpected value at position %d '
                    . '(expected an object key, detected a %s with value %s).',
                    $currentPosition,
                    get_class($propertyElement),
                    $propertyElement->__toString(),
                ));
            }
            $propertyElement->parse();
            $property = $propertyElement->getDecodedValue();

            // Separator of property and value (symbol ":")
            $this->readWhitespace();
            $currentPosition = $this->getCurrentFilePosition();
            $char = $this->readBytes(1);
            if ($char !== ':') {
                throw new UnexpectedValueException(sprintf(
                    'Invalid JSON object. Unexpected value at position %d (expected ":", received "%s").',
                    $currentPosition,
                    $char,
                ));
            }
            $this->readWhitespace();

            // Property Value
            $currentPosition = $this->getCurrentFilePosition();
            $valueElement = JsonElement::load($this->fileHandler);

            if ($this->useCache()) {
                $this->propertyPositions[$property] = $currentPosition;
            }

            yield $property => $valueElement;

            $totalProperties += 1;

            $this->setFilePosition($currentPosition);
            $valueElement->parse();
            $this->readWhitespace();

            $currentPosition = $this->getCurrentFilePosition();
            $char = $this->checkCurrentByte();
            if ($char === ',') {
                $this->readBytes(1);
                $this->readWhitespace();
                $char = $this->checkCurrentByte();
                if ($char === '}') {
                    throw new UnexpectedValueException(sprintf(
                        'Invalid JSON object. Unexpected end of object at position %d.',
                        $currentPosition,
                    ));
                }
            } elseif ($char === '}') {
                continue;
            } else {
                throw new UnexpectedValueException(sprintf(
                    'Invalid JSON object. Unexpected value at position %d (expected "," or "}", received "%s")',
                    $currentPosition,
                    $this->readBytes(10),
                ));
            }
        }

        // Read "}"
        $char = $this->readBytes(1);
        assert(
            $char === '}',
            throw new UnexpectedValueException(sprintf(
                'Invalid JSON object. Unexpected value at position %d (expected "}", received "%s").',
                $this->getCurrentFilePosition() - 1,
                $this->readBytes(10),
            )),
        );

        $this->setTotalProperties($totalProperties);
    }

    /**
     * Checks whether the offset (property name) exits in the object
     * (from ArrayAccess interface)
     *
     * @param mixed $offset The offset to be checked
     * @return bool Whether the offset exists in the object
     */
    public function offsetExists(mixed $offset): bool
    {
        if (!is_string($offset)) {
            return false;
        }

        // Try to check from cache (maybe the element was not completelly loaded)
        if ($this->useCache()) {
            if (array_key_exists($offset, $this->propertyPositions)) {
                return true;
            }
            if (isset($this->totalProperties)) {
                return false;
            }
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
     * Fetch an element (property) of the object by its offset (property name)
     * (from ArrayAccess interface)
     *
     * @param mixed $offset The offset (property name) of the element to be fetched
     * @return ?JsonElement The value of the element (wrapped by LazyJson\JsonElement) or null if it does not exist
     */
    public function offsetGet(mixed $offset): ?JsonElement
    {
        if (!is_string($offset)) {
            return null;
        }

        if ($this->useCache() && isset($this->propertyPositions[$offset])) {
            $this->setFilePosition($this->propertyPositions[$offset]);
            return JsonElement::load($this->fileHandler);
        }
        foreach ($this->getIterator() as $key => $value) {
            if ($key === $offset) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Throws an exception, since this class does not support updates in the object
     * (from ArrayAccess interface)
     *
     * @throws LogicException Always throws LogicException
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('ObjectElement does not allow to set properties dynamically.');
    }

    /**
     * Throws an exception, since this class does not support updates in the object
     * (from ArrayAccess interface)
     *
     * @throws LogicException Always throws LogicException
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('ObjectElement does not allow to unset properties dynamically.');
    }

    /**
     * {@inheritdoc}
     */
    protected function readCurrentJsonElement(): void
    {
        // Traversing the iterator to read the entire object
        // Then the file cursor will be placed in the next valid byte after the object
        foreach ($this->getIterator() as $element) {
            // Ignore $element
        }
    }

    /**
     * Return an iterator from the cache
     *
     * @return Traversable<string,JsonElement> An iterator that contains elements of type LazyJson\JsonElement
     */
    protected function getIteratorFromCache(): Traversable
    {
        $this->setFilePosition($this->startPosition);

        // Read "{"
        $char = $this->readBytes(1);
        assert(
            $char === '[',
            throw new UnexpectedValueException(sprintf(
                'Invalid JSON object. Unexpected value for array at position %d.',
                $this->getCurrentFilePosition() - 1,
            )),
        );
        $this->readWhitespace();

        $value = null;
        foreach ($this->propertyPositions as $property => $position) {
            $this->setFilePosition($position);
            $value = JsonElement::load($this->fileHandler, $this->useCache());
            yield $property => $value;
        }

        // Read the last value to ensure the cursor will advance to the end of the last element
        if ($value instanceof JsonElement) {
            $value->parse();
        }

        $this->readWhitespace();

        // Read the "}" to ensure the cursor will advance to the next element
        $char = $this->readBytes(1);
        assert(
            $char === '}',
            throw new UnexpectedValueException(sprintf(
                'Invalid JSON object. Unexpected value at position %d.',
                $this->getCurrentFilePosition() - 1,
            )),
        );
    }

    /**
     * Save the total number of properties
     *
     * @param int<0,max> $totalProperties The value to save
     * @return void
     */
    private function setTotalProperties(int $totalProperties): void
    {
        if (!isset($this->totalProperties)) {
            $this->totalProperties = $totalProperties;
        }
    }
}
