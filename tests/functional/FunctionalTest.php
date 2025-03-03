<?php

namespace LazyJson\Tests\Functional;

use PHPUnit\Framework\Attributes\{
    CoversNothing,
    TestDox,
};
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[TestDox('Functional')]
#[CoversNothing]
class FunctionalTest extends TestCase
{
    // Tests

    #[TestDox('Installing the lib.')]
    public function testInstall(): void
    {
        // Check
        if (!is_executable('/usr/bin/composer')) {
            $this->markTestSkipped('The composer binary is not available at /usr/bin/composer');
        }

        // Prepare
        $dir = $this->createTempDir();
        chdir($dir);
        $cmd = sprintf(
            '/usr/bin/composer require %s --working-dir=%s --no-interaction --no-progress --quiet',
            escapeshellarg('lazy-json/lazy-json:dev-main'),
            escapeshellarg($dir),
        );

        // Execute
        $output = [];
        $exitCode = null;
        exec($cmd, $output, $exitCode);

        // Expect
        $this->assertEquals(0, $exitCode);
        $this->assertDirectoryExists(sprintf('%s/vendor', $dir));
        $this->assertDirectoryExists(sprintf('%s/vendor/lazy-json', $dir));
        $this->assertDirectoryExists(sprintf('%s/vendor/lazy-json/lazy-json', $dir));
        $this->assertDirectoryDoesNotExist(sprintf('%s/vendor/lazy-json/lazy-json/.github', $dir));
        $this->assertDirectoryDoesNotExist(sprintf('%s/vendor/lazy-json/lazy-json/docs', $dir));
        $this->assertDirectoryDoesNotExist(sprintf('%s/vendor/lazy-json/lazy-json/examples', $dir));
        $this->assertDirectoryDoesNotExist(sprintf('%s/vendor/lazy-json/lazy-json/tests', $dir));
    }

    #[TestDox('Installing the lib and using it.')]
    public function testUsage(): void
    {
        // Check
        if (!is_executable('/usr/bin/composer')) {
            $this->markTestSkipped('The composer binary is not available at /usr/bin/composer');
        }

        // Prepare
        $dir = $this->createTempDir();
        chdir($dir);
        $cmd = sprintf(
            '/usr/bin/composer require %s --working-dir=%s --no-interaction --no-progress --quiet',
            escapeshellarg('lazy-json/lazy-json:dev-main'),
            escapeshellarg($dir),
        );
        $output = [];
        $exitCode = null;
        exec($cmd, $output, $exitCode);

        $content = json_encode([
            'a' => null,
            'b' => true,
            'c' => false,
            'd' => 1,
            'e' => 1.2e2,
            'f' => 'abc',
            'g' => [1, 2, 3],
            'h' => ['x' => 1, 'y' => 2],
        ]);
        file_put_contents(sprintf('%s/sample.json', $dir), $content);

        $content = <<<'EOF'
        <?php
        require(__DIR__ . '/vendor/autoload.php');

        $file = new SplFileObject(sprintf('%s/sample.json', __DIR__), 'r');

        $json = LazyJson\JsonElement::load($file);

        var_dump($json->getDecodedValue());
        EOF;
        file_put_contents(sprintf('%s/test.php', $dir), $content);

        // Execute
        $output = [];
        $exitCode = null;
        $cmd = sprintf('php %s', escapeshellarg(sprintf('%s/test.php', $dir)));
        exec($cmd, $output, $exitCode);
        $strOutput = trim(implode("\n", $output));

        // Expect
        $expectedOutput = <<<'EOF'
        object(stdClass)#6 (8) {
          ["a"]=>
          NULL
          ["b"]=>
          bool(true)
          ["c"]=>
          bool(false)
          ["d"]=>
          int(1)
          ["e"]=>
          int(120)
          ["f"]=>
          string(3) "abc"
          ["g"]=>
          array(3) {
            [0]=>
            int(1)
            [1]=>
            int(2)
            [2]=>
            int(3)
          }
          ["h"]=>
          object(stdClass)#10 (2) {
            ["x"]=>
            int(1)
            ["y"]=>
            int(2)
          }
        }
        EOF;

        $this->assertEquals(0, $exitCode);
        $this->assertEquals($expectedOutput, $strOutput);
    }

    /**
     * Create a temp dir to run the functional test
     * @return string The full path of the directory
     */
    private function createTempDir(): string
    {
        $dir = tempnam(sys_get_temp_dir(), 'test-functional-');
        if ($dir === false) {
            throw new RuntimeException('Failed to create temp dir to run functional test');
        }
        unlink($dir);
        $created = mkdir($dir, 0777, true);
        if ($created === false) {
            throw new RuntimeException('Failed to create temp dir to run functional test');
        }

        register_shutdown_function([self::class, 'clearTempDir'], $dir);

        return $dir;
    }

    /**
     * Clear a directory recursivelly
     * @param string $dir
     * @return void
     */
    protected static function clearTempDir(string $dir): void
    {
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $fullItem = sprintf('%s/%s', $dir, $item);
            if (is_file($fullItem)) {
                $deleted = unlink($fullItem);
                if ($deleted === false) {
                    throw new RuntimeException(sprintf('Failed to delete temp file: %s', $fullItem));
                }
            } elseif (is_dir($fullItem)) {
                self::clearTempDir($fullItem);
            }
        }
        $deleted = rmdir($dir);
        if ($deleted === false) {
            throw new RuntimeException(sprintf('Failed to delete temp dir: %s', $dir));
        }
    }
}
