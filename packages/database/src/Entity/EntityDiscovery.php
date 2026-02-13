<?php

declare(strict_types=1);

namespace Marko\Database\Entity;

use Marko\Core\Discovery\ClassFileParser;
use Marko\Database\Attributes\Table;
use ReflectionClass;

/**
 * Discovers entity classes with #[Table] attribute across modules.
 */
class EntityDiscovery
{
    public function __construct(
        private ClassFileParser $classFileParser,
    ) {}

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

        foreach ($this->classFileParser->findPhpFiles($path) as $file) {
            $filePath = $file->getPathname();
            $className = $this->classFileParser->extractClassName($filePath);

            if ($className === null) {
                continue;
            }

            if (!$this->classFileParser->loadClass($filePath, $className)) {
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
}
