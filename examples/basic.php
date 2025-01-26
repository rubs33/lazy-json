<?php
// Load the composer autoload to be able to use the provided classes
require(__DIR__ . '/../vendor/autoload.php');

// Open a file using SplFileObject with mode "r" (read)
$file = new SplFileObject(__DIR__ . '/sample.json', 'r');

// Load the JSON into a lazy object
$lazyObj = LazyJson\JsonElement::load($file);

// Now you can interact with $lazyObj as if it was a decoded element retrieved from "json_decode".
// However, each element will be wrapped by a LazyJson\JsonElement class.
// To retrieve the real decoded value of an element, use "getDecodedValue" in any point of the "data tree".

// Printing the name of the first author
printf("Author name: %s\n", $lazyObj->authors[0]->name->getDecodedValue());

// Getting the decoded array of authors
$authorsArray = $lazyObj->authors->getDecodedValue();
var_dump($authorsArray);
