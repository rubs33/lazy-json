<?php
// Load the composer autoload to be able to use the provided classes
require(__DIR__ . '/../vendor/autoload.php');

// Open a file using SplFileObject with mode "r" (read)
$file = new SplFileObject(__DIR__ . '/sample.json', 'r');

// Control whether you want to cache useful data for arrays/objects to improve performance for random access.
// Using the cache, once an array element or object property is parsed, its position in the file is saved.
// This way, if the same array position or object property is requested again, the parser does not need to traverse the
// entire JSON element again.
// Cache is activated by default, and you can deactivate it if you are handling a very large array or object.
$useCache = true;

// Load the JSON into a lazy object
$lazyObj = LazyJson\JsonElement::load($file, $useCache);

// The method above will detect the type of the JSON element (based on the first byte) and create an instance accordingly.
// The instance will be a child class of LazyJson\JsonElement and can be one of:
// - LazyJson\NullElement
// - LazyJson\BoolElement
// - LazyJson\NumberElement
// - LazyJson\StringElement
// - LazyJson\ArrayElement
// - LazyJson\ObjectElement

// Note: the element will not be fully parsed imediatelly. The parse process happens on-demand.

// You can check the exact type of the element using the operator "instanceof"
if ($lazyObj instanceof LazyJson\ObjectElement) {
    printf("The root element is an instance of LazyJson\ObjectElement\n");
}
if ($lazyObj->authors instanceof LazyJson\ArrayElement) {
    printf("The element \$.authors is an instance of LazyJson\ArrayElement\n");
}

// You can navigate over the lazy object using "->" or "[]" to access object properties and "[]" to access array elements.
// Object Elements implement magic methods __get and __isset, and the ArrayAccess and Countable interface.
// Array Elements implement ArrayAccess and Countable interface.
// You can only get values from the wrapped objects, but never set/unset values (updates are not allowed).
$author = $lazyObj->authors[0];

// It also works:
$author = $lazyObj['authors'][0];

// Note: each time you request an element, it will be loaded on-demand, so the object will always return a different child instance.
if ($lazyObj->authors[0] !== $lazyObj->authors[0]) {
    printf("My child elements are loaded in different instances.\n");
}

// Checking the count of authors
printf("Count of authors: %d\n", count($lazyObj->authors));

// If you access a targed element of the JSON tree, you must call "getDecodedValue" to get the real decoded value (PHP value).
// Remember: all the elements of the JSON are wrapped by the LazyJson\JsonElement class for consistency,
// even the simple types like booleans and null.
printf("Author name: %s\n", $author->name->getDecodedValue());

// All the JSON elements implements "__toString" for your convenience:
printf("Author name: %s\n", $author->name);

// You can call "getDecodedValue" over any element of the lazy object
var_dump($lazyObj->authors->getDecodedValue());

/* Output:
 * array(2) {
 *   [0]=>
 *   object(stdClass)#10 (2) {
 *     ["name"]=>
 *     string(3) "Foo"
 *     ["email"]=>
 *     string(11) "foo@bar.com"
 *   }
 *   [1]=>
 *   object(stdClass)#5 (2) {
 *     ["name"]=>
 *     string(3) "Bar"
 *     ["email"]=>
 *     string(11) "bar@baz.com"
 *   }
 * }
 */

// Note that calling "getDecodedValue" over an array or an object, all the child elements will be parsed and decoded in cascade.
// As consequence, the resulting variable might consume a lot of memory.
// The value returned by "getDecodedValue" will be similar to the result of the traditional "json_decode".
// It also accepts a parameter "$associative" if you prefer decoding JSON objects into associative arrays.

// Decoding a JSON object using associative array
var_dump($lazyObj->authors->getDecodedValue(true));

/* Output
 * array(2) {
 *   [0]=>
 *   array(2) {
 *     ["name"]=>
 *     string(3) "Foo"
 *     ["email"]=>
 *     string(11) "foo@bar.com"
 *   }
 *   [1]=>
 *   array(2) {
 *     ["name"]=>
 *     string(3) "Bar"
 *     ["email"]=>
 *     string(11) "bar@baz.com"
 *   }
 * }
 */

// The interface JsonSerializable is also supported
printf("JSON-encoded: %s\n", json_encode($lazyObj->authors[0]));

// You can get numeric values with scientific-notation
$speedOfLight = $lazyObj->speed_of_light->getDecodedValue();
printf("Speed of light: %d\n", $speedOfLight);

// You can get unicode texts in UTF-8
$unicodeText = $lazyObj->unicode->getDecodedValue();
printf("Unicode text:\n%s\n", $unicodeText);

// The main benefit of this library is to avoid consuming a lot of memory when handling large arrays, objects, or strings.
// For this reason, LazyJson\ArrayElement, LazyJson\ObjectElement and LazyJson\StringElement implement the IteratorAggregate interface
// to give you the ability to traverse the elements of the array, the property/value pairs of the objects, and the UTF-8 chars of a string.

// Traversing the list of authors, one author will be fetched on each iteration.
// Remember that the child element will be a lazy/wrapped object too, so you do not need to worry about the memory if the object is large.
foreach ($lazyObj->authors as $i => $wrappedAuthor) {
    printf("Name of the author %d: %s\n", $i, $wrappedAuthor->name->getDecodedValue());
}

// Traversing the list of properties of an object.
// For objects, the property name is returned as a raw PHP string (usually it is a small string), but the property value is a lazy/wrapped object.
foreach ($lazyObj as $property => $wrappedValue) {
    $rawValue = $wrappedValue->getDecodedValue();
    printf("Property of the object: %s / type = %s / value = %s\n", $property, gettype($rawValue), var_export($rawValue, true));
}

// Traversing the list of UTF-8 chars of a string
// In this case, each iteration will get a raw PHP string (with a single UTF-8 char), and NOT a wrapped object
foreach ($lazyObj->unicode as $i => $char) {
    printf("Unicode char at position %d: '%s'\n", $i, $char);
}
