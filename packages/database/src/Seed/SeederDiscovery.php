<?php

declare(strict_types=1);

namespace Marko\Database\Seed;

use Marko\Core\Discovery\ClassFileParser;
use ReflectionClass;

/**
 * Discovers seeder classes in Seed directories.
 */
readonly class SeederDiscovery implements SeederDiscoveryInterface
{
    public function __construct(
        private ClassFileParser $classFileParser,
    ) {}

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
}
