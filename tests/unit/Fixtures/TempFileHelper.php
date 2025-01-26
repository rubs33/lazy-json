<?php

namespace LazyJson\Tests\Unit\Fixtures;

use RuntimeException;
use SplFileObject;

use function file_exists;
use function file_put_contents;
use function sprintf;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class TempFileHelper
{
    /**
     * Create a file to be used by a test scenario.
     *
     * @param  string|iterable $fileContent The content to be used in the file
     * @param string $mode Mode to open the file
     * @return SplFileObject An instance of the file already opened to read
     * @throws RuntimeException If the file cannot be created
     */
    public static function createTempFile(string|iterable $fileContent, $mode = 'r'): SplFileObject
    {
        $dir = sys_get_temp_dir();
        $prefix = 'test-';

        $filename = tempnam($dir, $prefix);
        if ($filename === false) {
            throw new RuntimeException(sprintf('Failed to create a JSON file for tests: %s', $filename));
        }

        register_shutdown_function([self::class, 'clearTempFile'], $filename);

        if (is_string($fileContent)) {
            $result = file_put_contents($filename, $fileContent);
            if ($result === false) {
                throw new RuntimeException(sprintf('Failed to create a JSON file for tests: %s', $filename));
            }
        } else {
            $fileHandler = fopen($filename, 'w');
            if ($fileHandler === false) {
                throw new RuntimeException(sprintf('Failed to create a JSON file for tests: %s', $filename));
            }
            foreach ($fileContent as $chunk) {
                $result = fwrite($fileHandler, $chunk);
                if ($result === false) {
                    throw new RuntimeException(sprintf('Failed to create a JSON file for tests: %s', $filename));
                }
            }
            fclose($fileHandler);
        }

        return new SplFileObject($filename, $mode);
    }

    /**
     * Clear the dynamically-created file.
     *
     * @param  string $file The file to be removed
     * @return void
     * @throws RuntimeException If the file cannot be created
     */
    protected static function clearTempFile($file): void
    {
        $result = file_exists($file) ? unlink($file) : true;
        if ($result === false) {
            throw new RuntimeException(sprintf('Failed to delete temp file: %s', $file));
        }
    }
}