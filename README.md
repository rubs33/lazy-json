# LazyJson

## Description

LazyJson is a PHP library that allows you to navigate a JSON file through a lazy-loaded, object-oriented interface.

Unlike `json_decode`, which loads the entire JSON file into memory at once, this library parses only the necessary elements on demand, based on the operations you perform during navigation.

Its primary goal is to provide a **memory-efficient** way to **access** JSON data rather than modifying it. While it can be used to validate a JSON structure, it assumes the JSON is well-formed and only parses elements as needed to return the requested data.

### Advantages:
* **Memory-efficient**: Optimized for large JSON files, supporting arrays, objects, and strings, with minimal memory usage during navigation.
* **Intuitive**: Navigate the JSON as if it were parsed by `json_decode`, without needing to load the entire structure into memory.
* **Zero dependencies**: No third-party libraries required, only standard PHP extensions.
* **PHP 7.4+ compatible**.
* **High test coverage**: Rigorous unit tests ensure reliability.
* **MIT License**: You have the freedom to use, modify, and distribute it in both open-source and proprietary projects, with no warranty.

### Use cases

For a JSON file with:

**... a large array**, you can:

1. Access a specific element without loading the entire array into memory.
2. Iterate over the array, keeping only one element in memory at a time.
3. Count the elements without loading the whole array.

**... a large object**, you can:

1. Access specific properties without parsing the entire object.
2. Iterate over the object, keeping only one key-value pair in memory at a time.

**... a large string**, you can:

1. Iterate over the string, processing one UTF-8 character at a time (e.g., stream it to an HTTP response, decode Base64, or save to a file).

## Requirements

* PHP 8.1 or higher
* ext-ctype
* ext-json
* ext-spl

## Installation

To use this library in PHP 7.4, you can install it via [Composer](https://getcomposer.org/):

```sh
$ composer require 'lazy-json/lazy-json:dev-php7'
```

To use this library in PHP 8.1+, you can install using:

```sh
$ composer require 'lazy-json/lazy-json'
```

## Documentation / API

To know how to use it, check the page [Usage](docs/Usage.md).

To have details about public methods, access the [API spec](docs/API.md).

You might also want to see the [examples](/examples).
