# Lazy Json Usage

This document explains how to use the LazyJson library.
If you are interested in the API specs of the classes, check [API](API.md)

## Loading a JSON file

To construct a lazy object from a JSON file, open the file using [`SplFileObject`](https://www.php.net/SplFileObject) with mode `r` (read) and use it in the static method `LazyJson\JsonElement::load()`. Example:

```
$file = new \SplFileObject('/path/to/my/file.json', 'r');

$lazyObj = \LazyJson\JsonElement::load($file);
```

The method `load` will detect the type of the root element of the JSON file (using an heuristic) and return an appropriate instance of a child class derived from `LazyJson\JsonElement`. The object will be an instance of one of these wrapper classes:

* `LazyJson::NullElement`
* `LazyJson::BooleanElement`
* `LazyJson::NumberElement`
* `LazyJson::StringElement`
* `LazyJson::ArrayElement`
* `LazyJson::ObjectElement`

Note: the method `load` will **not** parse the entire file imediatelly. It will just read the minimum number of bytes to detect the type of the root element of the JSON file. For example, if the file starts with the byte (char) `t`, it assumes the file content has a boolean value with the value `true`, since it is the unique valid JSON value that starts with the letter `t`. If it starts with `[`, it assumes the file has an array. And so on.

Note: the mentioned classes are used as wrapper classes to traverse the JSON hierarchically. This way, if the root element is an array or an object, it will work like a tree, where the root element can traverse the child elements and these child elements will always be an instance of `LazyJson\JsonElement` and can be traversed too (if they are arrays or objects).

## Detecting the type of the element

To detect the type of a lazy JSON element, just use PHP's [`instanceof`](https://www.php.net/instanceof). Example:

```
$lazyObj = \LazyJson\JsonElement::load($file);

if ($lazyObj instanceof \LazyJson\ObjectElement) {
    ...
}
```

## Traversing the JSON tree

Usually the root element of a JSON file will be an array or an object. If the method `load` detects the root element is not an array nor an object, you will just be able to manipulate a simple element. However, if the root element is an array or an object, these elements can be traversed to fetch their child elements.

There are 2 ways to traverse an array or object: (1) iterating over the child elements; or (2) accessing a specific random child.

### Iterating over child elements

The classes `LazyJson\ArrayElement` and `LazyJson\ObjectElement` implements the PHP interface [`IteratorAggregate`](https://www.php.net/IteratorAggregate). This way, you can iterate over the elements:

```
$lazyObj = \LazyJson\JsonElement::load($file);

foreach ($lazyObj as $key => $value) {
    ...
}
```

If you are traversing an array, the `$key` will be an integer (starting from `0`), representing the element position in the array. The `$value` will be an object of a wrapper class.

If you are traversing an object, the `$key` will be a native PHP string (a decoded value), representing a property name. The `$value` will be an object of a wrapper class.

Note: if you store the iterator in a variable, you cannot iterate over the same iterator instance twice. That means the code bellow will **not** work.

```
$iterator = $lazyObj->getIterator();

foreach ($iterator as $key => $value) {
    ...
}

// This second loop will fail, since the iterator has already been traversed
foreach ($iterator as $key => $value) {
    ...
}
```

Instead, you can just call the method twice:

```
foreach ($lazyObj->getIterator() as $key => $value) {
    ...
}

foreach ($lazyObj->getIterator() as $key => $value) {
    ...
}
```

### Accessing random child elements

The class `LazyJson\ArrayElement` implements the PHP interfaces [`ArrayAccess`](https://www.php.net/ArrayAccess) and [`Countable`](https://www.php.net/ArrayAccess). That means the objects of this class behaves like a regular PHP array, with few differences.

To access a random child element of an array, you just need to use `[]` to access the desired position.

The class `LazyJson\ObjectElement` implements the PHP interfaces [`ArrayAccess`](https://www.php.net/ArrayAccess) and [`Countable`](https://www.php.net/ArrayAccess). Additionally, it implements the magic methods [`__get`](https://www.php.net/manual/en/language.oop5.overloading.php#object.get) and [`__isset`](https://www.php.net/manual/en/language.oop5.overloading.php#object.isset). That means the objects of this class behaves like a regular PHP object, with few differences.

To access a random child element of an object, you just need to use `->` or `[]` to access the child property.

```
/* Assuming the file content is:
 * {
 *   "x": [
 *     1,
 *     2,
 *     3
 *   ]
 * }
 */
$lazyObj = \LazyJson\JsonElement::load($file);

$childElement = $lazyObj->x[0];
```

In the example above, the `$childElement` will be an object of type `LazyJson\NumberElement`, that wraps the value `1`.

As mentioned, you can also access object properties using `[]`. Example:
```
$childElement = $lazyObj['x'][0];
```

Counting the number of elements:

```
$totalPropertiesOfObject = count($lazyObj);
$totalElementsOfArray = count($lazyObj->x);
```

Checking if a position of the array or a property of the object exists:

```
$propertyExists = isset($lazyObj->x);
$positionExists = isset($lazyObj->x[0]);
```

Note: you cannot set or unset values from the wrapped classes. They were planned to be read-only.

## Decoding a value

As you could see, when you traverse a JSON tree, accessing objects and arrays, you will always reach to a desired target element that will be represented by a wrapper class. To decode the value of an element wrapped by a wrapper class, you just need to call the method `getDecodedValue`. It will return the raw PHP value that corresponds to the read JSON value.

This method receives a boolean parameter to indicate whether the result must use associative arrays or objects (of class [`stdClass`](https://www.php.net/stdClass)), just like PHP's function [`json_decode`](https://www.php.net/json_decode).

```
$decodedValue = $lazyObj->x[0]->getDecodedValue();
```

Note: if you decode a large object, array or string, it will consume a lot of memory, just like `json_decode` would do. Considering you are using this class to avoid consuming a lot o memory, it might be useful to not decode large elements. If you know an element is large, you can traverse it using the iterator.

## Iterating over a string

Since a JSON can contain large strings too, it is useful to have a way to iterate over these strings to avoid consuming a lot of memory. For this reason, the class `LazyJson\StringElement` also implements the `IteratorAggregate`.

The difference between the iterator returned by `StringElement` and the iterator returned by `ArrayElement` and `ObjectElement` is that `StringElement`'s iterator returns a PHP raw string on each iteration (a single unicode symbol per iteration). This way, you can decide what to do with the string content while you traverse it. For exemple, you can store the decoded value in a temporary file:

```
// $considering $lazyObj is a LazyJson\StringElement

$tempFilename = tempnam(sys_get_temp_dir(), 'temp-file-');
$tempFile = fopen($tempFilename, 'w');
foreach ($lazyObj as $unicodeSymbol) {
    fwrite($tempFile, $unicodeSymbol);
}
fclose($tempFile);
```

Maybe your string is encoded with an algorithm like Bas64, so you can decide to decode it on-demand and send the decoded value to a destiny (incrementally), instead of reading the entire string, put it in the memory and use `base64_decode` over it.
