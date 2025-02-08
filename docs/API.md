# LazyJson API

This document exposes the LazyJson API with the available classes and methods.

If you are interested to know how to use it, you can see the [Usage](Usage.md).

## Classes

### abstract `LazyJson\JsonElement` implements [`JsonSerializable`](https://www.php.net/JsonSerializable)

Abstract class for wrapper classes of JSON elements.

#### public static function `load(SplFileObject $fileHandler, bool $useCache = true)`: LazyJson\JsonElement

This is the main method to load a JSON file and return a `LazyJson\JsonElement`.

Note: it returns a lazzy object. That means the file is not parsed.
This method only detects which type of JSON element the cursor of the file handler points to based on the current byte.
If you want to parse the entire file, use the method `parse`.

Params:

* `$fileHandler`: The JSON file to be read/parsed.
* `$useCache`: Whether the class must use cache to improve performance (default `true`)

Note: when cache is activated, the position of the child elements of an array or an object are stored in memory. This way, it improves the performance, since the object does not need to parse all the child elements again to reach the desired array position or object property.

#### public function `getDecodedValue(bool $associative = false)`: mixed

Returns the decoded value of the current JSON element.

Params:

* `$associative` - Whether the decoded JSON objects will be represented by associative arrays (default `false`).

#### public function `parse()`: void

Parses the current elements and advance the file cursor to the next byte after the current element.

If you want to validate whether the current JSON file is valid, you can call this method and check for exceptions if the JSON is not valid.

Note: only the current element of the file will be parsed. If the element is a string that is a child of an array, `parse` will stop after reaching the end-of-string, represented by double quotes. That means: if you run `parse` over the root element of a JSON file, it will not check the remaining bytes after the end of the current element.

#### public function `__toString()`: string

Magic method to return the object as a string, when requested.


#### public function `jsonSerialize()`

Return the serializable value for `json_encode` (from [`JsonSerializable`](https://www.php.net/JsonSerializable) interface)

---

### `LazyJson\NullElement` extends `LazyJson\JsonElement`

Wrapper class for value `null` of a JSON file.

---

### `LazyJson\BooleanElement` extends `LazyJson\JsonElement`

Wrapper class for boolean values of a JSON file.

---

### `LazyJson\NumberElement` extends `LazyJson\JsonElement`

Wrapper class for numeric values of a JSON file.

#### public function `getRawValue()`: string

Returns the original numeric value read from the JSON file.
It is useful if the JSON file contains a very big number that would be converted to [+INF](https://www.php.net/manual/en/math.constants.php#constant.inf) (positive infinite) or -INF (negative infinite) in the PHP scope.

---

### `LazyJson\StringElement` extends `LazyJson\JsonElement` implements [`IteratorAggregate`](https://www.php.net/IteratorAggregate)

Wrapper class for string values of a JSON file.

#### public function `getIterator()`: [Traversable](https://www.php.net/Traversable)\<string\>

Returns an iterator to traverse each decoded unicode symbol from the string element (from [`IteratorAggregate`](https://www.php.net/IteratorAggregate) interface).

---

### `LazyJson\ArrayElement` extends `LazyJson\JsonElement` implements [`ArrayAccess`](https://www.php.net/ArrayAccess), [`Countable`](https://www.php.net/Countable), [`IteratorAggregate`](https://www.php.net/IteratorAggregate)

Wrapper class for array values of a JSON file.

#### public function `getIterator()`: [Traversable](https://www.php.net/Traversable)\<int,LazyJson\JsonElement\>

Returns an iterator to traverse each child element of the array (from [`IteratorAggregate`](https://www.php.net/IteratorAggregate) interface).

#### public function `count()`: int

Returns the total number of elements of the array (from [`Countable`](https://www.php.net/Countable) interface).

#### public function `offsetExists(mixed $offset)`: bool

Returns whether an element exists in the position `$offset` of the current array element (from [`ArrayAccess`](https://www.php.net/ArrayAccess) interface).

This method is called when the operator `[]` is used over the object that is being tested by an `isset` call.

Note: if the offset exists and points to the value `null` in the JSON, this method will return true.

Example:

```
$exists = isset($lazyObj[1]);
$exists = $lazyObj->offsetExists(1);
```

#### public function `offsetGet(mixed $offset)`: ?LazyJson\JsonElement

Returns an element of the position `$offset` of the current array element or `null` if it does not exist (from [`ArrayAccess`](https://www.php.net/ArrayAccess) interface).

This method is called when the operator `[]` is used over the object in a context of consulting the value.

Note: if the offset exists and points to the value `null` in the JSON, this method will return an instance of `LazyJson\NullElement`, that will wrap the value `null`.

Example:

```
$value = $lazyObj[1];
$value = $lazyObj->offsetGet(1);
```

---

### `LazyJson\ObjectElement` extends `LazyJson\JsonElement` implements [`ArrayAccess`](https://www.php.net/ArrayAccess), [`Countable`](https://www.php.net/Countable), [`IteratorAggregate`](https://www.php.net/IteratorAggregate)

Wrapper class for object values of a JSON file.

#### public function `getIterator()`: [Traversable](https://www.php.net/Traversable)\<string,LazyJson\JsonElement\>

Returns an iterator to traverse each child element (property) of the object (from [`IteratorAggregate`](https://www.php.net/IteratorAggregate) interface).

#### public function `count()`: int

Returns the total number of properties of the object (from [`Countable`](https://www.php.net/Countable) interface).

#### public function `offsetExists(mixed $offset)`: bool

Returns whether a property exists with the name `$offset` of the current object element (from [`ArrayAccess`](https://www.php.net/ArrayAccess) interface).

This method is called when the operator `[]` is used over the object that is being tested by an `isset` call.

Note: if the property exists and points to the value `null` in the JSON, this method will return `true`.

Example:

```
$exists = isset($lazyObj['prop']);
$exists = $lazyObj->offsetExists('prop');
```

#### public function `offsetGet(mixed $offset)`: ?LazyJson\JsonElement

Returns the property with the name `$offset` of the current object element or `null` if it does not exist (from [`ArrayAccess`](https://www.php.net/ArrayAccess) interface).

This method is called when the operator `[]` is used over the object in a context of consulting the value.

Note: if the property exists and points to the value `null` in the JSON, this method will return an instance of `LazyJson\NullElement`, that will wrap the value `null`.

Example:

```
$value = $lazyObj['prop'];
$value = $lazyObj->offsetGet('prop');
```

#### public function `__isset(string $name)`: bool

Magic method that returns whether a property exists in the current object element.

This method is called when the operator `->` is used over the object that is being tested by an `isset` call.

Note: if the property exists and points to the value `null` in the JSON, this method will return `true`.

Example:

```
$exists = isset($lazyObj->prop);
$exists = $lazyObj->__isset('prop');
```

#### public function `__get(string $name)`: ?LazyJson\JsonElement

Magic method that returns a property of the JSON object or `null` if it does not exist.

This method is called when the operator `->` is used in a read context.

Note: if the property exists and points to the value `null` in the JSON, this method will return an instance of `LazyJson\NullElement`, that will wrap the value `null`.

Example:

```
$value = $lazyObj->prop;
$value = $lazyObj->__get('prop');
```
