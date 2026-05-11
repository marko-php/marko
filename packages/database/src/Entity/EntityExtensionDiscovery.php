<?php

declare(strict_types=1);

namespace Marko\Database\Entity;

use Marko\Core\Discovery\ClassFileParser;
use Marko\Database\Attributes\ExtensionOf;
use Marko\Database\Exceptions\EntityException;
use ReflectionClass;

/**
 * Discovers entity extension classes with #[ExtensionOf] attribute across modules.
 */
class EntityExtensionDiscovery
{
    public function __construct(
        private ClassFileParser $classFileParser,
    ) {}

    /**
     * Discover extensions in vendor/vendor-name/package-name/src/EntityExtension directories.
     *
     * @return array<array{0: class-string<Entity>, 1: class-string<EntityExtension>}>
     *
     * @throws EntityException
     */
    public function discoverInVendor(string $vendorPath): array
    {
        if (!is_dir($vendorPath)) {
            return [];
        }

        $pairs = [];

        foreach (glob($vendorPath . '/*/*/src/EntityExtension', GLOB_ONLYDIR) as $dir) {
            $pairs = array_merge($pairs, $this->discoverInPath($dir));
        }

        return $pairs;
    }

    /**
     * Discover extensions in modules/vendor-name/module-name/src/EntityExtension directories.
     *
     * @return array<array{0: class-string<Entity>, 1: class-string<EntityExtension>}>
     *
     * @throws EntityException
     */
    public function discoverInModules(string $modulesPath): array
    {
        if (!is_dir($modulesPath)) {
            return [];
        }

        $pairs = [];

        foreach (glob($modulesPath . '/*/*/src/EntityExtension', GLOB_ONLYDIR) as $dir) {
            $pairs = array_merge($pairs, $this->discoverInPath($dir));
        }

        return $pairs;
    }

    /**
     * Discover extensions in app/module-name/EntityExtension and app/module-name/src/EntityExtension directories.
     *
     * @return array<array{0: class-string<Entity>, 1: class-string<EntityExtension>}>
     *
     * @throws EntityException
     */
    public function discoverInApp(string $appPath): array
    {
        if (!is_dir($appPath)) {
            return [];
        }

        $pairs = [];

        foreach (glob($appPath . '/*/EntityExtension', GLOB_ONLYDIR) as $dir) {
            $pairs = array_merge($pairs, $this->discoverInPath($dir));
        }

        foreach (glob($appPath . '/*/src/EntityExtension', GLOB_ONLYDIR) as $dir) {
            $pairs = array_merge($pairs, $this->discoverInPath($dir));
        }

        return $pairs;
    }

    /**
     * Discover extensions in a specific path.
     *
     * @return array<array{0: class-string<Entity>, 1: class-string<EntityExtension>}>
     *
     * @throws EntityException
     */
    public function discoverInPath(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $pairs = [];

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

            // Must extend EntityExtension
            if (!$reflection->isSubclassOf(EntityExtension::class)) {
                continue;
            }

            // Must have #[ExtensionOf] attribute
            $extensionOfAttrs = $reflection->getAttributes(ExtensionOf::class);
            if (count($extensionOfAttrs) === 0) {
                continue;
            }

            $entityClass = $extensionOfAttrs[0]->newInstance()->entityClass;

            // Validate that target entity extends Entity
            if (!is_subclass_of($entityClass, Entity::class)) {
                throw EntityException::extensionOfTargetNotEntity($className, $entityClass);
            }

            $pairs[] = [$entityClass, $className];
        }

        return $pairs;
    }
}
