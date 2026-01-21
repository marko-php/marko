<?php

declare(strict_types=1);

namespace Marko\Database\Entity;

use Marko\Database\Attributes\Table;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

/**
 * Discovers entity classes with #[Table] attribute across modules.
 */
class EntityDiscovery
{
    /**
     * Discover entities in vendor/vendor-name/package-name/src/Entity directories.
     *
     * @return array<class-string<Entity>>
     */
    public function discoverInVendor(
        string $vendorPath,
    ): array {
        if (!is_dir($vendorPath)) {
            return [];
        }

        $entities = [];

        foreach (glob($vendorPath . '/*/*/src/Entity', GLOB_ONLYDIR) as $entityDir) {
            $entities = array_merge($entities, $this->discoverInPath($entityDir));
        }

        return $entities;
    }

    /**
     * Discover entities in modules/vendor-name/module-name/src/Entity directories.
     *
     * @return array<class-string<Entity>>
     */
    public function discoverInModules(
        string $modulesPath,
    ): array {
        if (!is_dir($modulesPath)) {
            return [];
        }

        $entities = [];

        foreach (glob($modulesPath . '/*/*/src/Entity', GLOB_ONLYDIR) as $entityDir) {
            $entities = array_merge($entities, $this->discoverInPath($entityDir));
        }

        return $entities;
    }

    /**
     * Discover entities in app/module-name/Entity directories.
     *
     * @return array<class-string<Entity>>
     */
    public function discoverInApp(
        string $appPath,
    ): array {
        if (!is_dir($appPath)) {
            return [];
        }

        $entities = [];

        foreach (glob($appPath . '/*/Entity', GLOB_ONLYDIR) as $entityDir) {
            $entities = array_merge($entities, $this->discoverInPath($entityDir));
        }

        return $entities;
    }

    /**
     * Discover entities in a specific path.
     *
     * @return array<class-string<Entity>>
     */
    public function discoverInPath(
        string $path,
    ): array {
        if (!is_dir($path)) {
            return [];
        }

        $entities = [];

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

            // Must have #[Table] attribute
            $tableAttributes = $reflection->getAttributes(Table::class);
            if (count($tableAttributes) === 0) {
                continue;
            }

            // Must extend Entity
            if (!$reflection->isSubclassOf(Entity::class)) {
                continue;
            }

            $entities[] = $className;
        }

        return $entities;
    }

    /**
     * Discover all entities across vendor, modules, and app paths.
     *
     * @return array<class-string<Entity>>
     */
    public function discoverAll(
        string $vendorPath,
        string $modulesPath,
        string $appPath,
    ): array {
        return array_merge(
            $this->discoverInVendor($vendorPath),
            $this->discoverInModules($modulesPath),
            $this->discoverInApp($appPath),
        );
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
