<?php

declare(strict_types=1);

namespace Marko\Database\Seed;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

/**
 * Discovers seeder classes in Seed directories.
 */
class SeederDiscovery
{
    /**
     * Discover seeders in vendor/vendor-name/package-name/Seed directories.
     *
     * @return array<SeederDefinition>
     */
    public function discoverInVendor(
        string $vendorPath,
    ): array {
        if (!is_dir($vendorPath)) {
            return [];
        }

        $seeders = [];

        foreach (glob($vendorPath . '/*/*/Seed', GLOB_ONLYDIR) as $seedDir) {
            $seeders = array_merge($seeders, $this->discoverInPath($seedDir));
        }

        return $seeders;
    }

    /**
     * Discover seeders in modules/vendor-name/module-name/Seed directories.
     *
     * @return array<SeederDefinition>
     */
    public function discoverInModules(
        string $modulesPath,
    ): array {
        if (!is_dir($modulesPath)) {
            return [];
        }

        $seeders = [];

        foreach (glob($modulesPath . '/*/*/Seed', GLOB_ONLYDIR) as $seedDir) {
            $seeders = array_merge($seeders, $this->discoverInPath($seedDir));
        }

        return $seeders;
    }

    /**
     * Discover seeders in app/module-name/Seed directories.
     *
     * @return array<SeederDefinition>
     */
    public function discoverInApp(
        string $appPath,
    ): array {
        if (!is_dir($appPath)) {
            return [];
        }

        $seeders = [];

        foreach (glob($appPath . '/*/Seed', GLOB_ONLYDIR) as $seedDir) {
            $seeders = array_merge($seeders, $this->discoverInPath($seedDir));
        }

        return $seeders;
    }

    /**
     * Discover seeders in a specific path.
     *
     * @return array<SeederDefinition>
     */
    public function discoverInPath(
        string $path,
    ): array {
        if (!is_dir($path)) {
            return [];
        }

        $seeders = [];

        foreach ($this->findPhpFiles($path) as $file) {
            $filePath = $file->getPathname();
            $className = $this->extractClassName($filePath);

            if ($className === null) {
                continue;
            }

            require_once $filePath;

            if (!class_exists($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);
            $attributes = $reflection->getAttributes(Seeder::class);

            if (count($attributes) === 0) {
                continue;
            }

            $attribute = $attributes[0]->newInstance();
            $seeders[] = new SeederDefinition(
                seederClass: $className,
                name: $attribute->name,
                order: $attribute->order,
            );
        }

        return $seeders;
    }

    /**
     * Extract the fully qualified class name from a PHP file.
     */
    private function extractClassName(
        string $filePath,
    ): ?string {
        $contents = file_get_contents($filePath);

        if ($contents === false) {
            return null;
        }

        $namespace = null;
        $class = null;

        if (preg_match('/namespace\s+([^;]+);/', $contents, $matches)) {
            $namespace = $matches[1];
        }

        if (preg_match('/class\s+(\w+)/', $contents, $matches)) {
            $class = $matches[1];
        }

        if ($class === null) {
            return null;
        }

        return $namespace !== null ? $namespace . '\\' . $class : $class;
    }

    /**
     * Find all PHP files in a directory recursively.
     *
     * @return iterable<SplFileInfo>
     */
    private function findPhpFiles(
        string $directory,
    ): iterable {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory),
        );

        foreach ($iterator as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            yield $file;
        }
    }
}
