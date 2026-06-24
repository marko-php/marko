<?php

declare(strict_types=1);

namespace Marko\Testing;

use Marko\Core\Exceptions\ModuleException;
use Marko\Core\Module\ManifestParser;
use Marko\Core\Module\ModuleAutoloader;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;

/**
 * Base test case for Marko applications.
 *
 * Registers PSR-4 autoloaders for app/* and modules/* before tests run so that
 * module classes resolve without per-project Composer classmap configuration.
 * No container or application boot is performed.
 */
class TestCase extends PhpUnitTestCase
{
    /** @var array<string, true> Tracks project roots already registered in this process */
    private static array $registeredRoots = [];

    /**
     * Set up autoloaders before each test.
     *
     * @throws ModuleException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->registerModuleAutoloaders();
    }

    /**
     * Register PSR-4 autoloaders for app/* and modules/* modules.
     *
     * Safe to call multiple times; registration is skipped for roots that were
     * already processed in this PHP process.
     *
     * @throws ModuleException
     */
    protected function registerModuleAutoloaders(): void
    {
        $root = $this->projectRoot();

        if (isset(self::$registeredRoots[$root])) {
            return;
        }

        self::$registeredRoots[$root] = true;

        $autoloader = new ModuleAutoloader(
            modulesPath: $root . '/modules',
            appPath: $root . '/app',
            parser: new ManifestParser(),
        );

        $autoloader->register();
    }

    /**
     * Locate the project root by walking up the directory tree from the current
     * working directory until a directory containing vendor/ is found.
     *
     * Returns the deepest ancestor (or the cwd itself) that contains vendor/.
     */
    protected function projectRoot(): string
    {
        $dir = getcwd();

        if ($dir === false) {
            return '';
        }

        while ($dir !== dirname($dir)) {
            if (is_dir($dir . '/vendor')) {
                return $dir;
            }

            $dir = dirname($dir);
        }

        return $dir;
    }
}
