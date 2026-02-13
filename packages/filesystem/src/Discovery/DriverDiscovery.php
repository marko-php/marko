<?php

declare(strict_types=1);

namespace Marko\Filesystem\Discovery;

use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Module\ModuleManifest;
use Marko\Filesystem\Attributes\FilesystemDriver;
use Marko\Filesystem\Contracts\FilesystemDriverFactoryInterface;
use Marko\Filesystem\Exceptions\FilesystemException;
use ReflectionClass;

/**
 * Discovers filesystem driver factories in module src directories.
 */
readonly class DriverDiscovery
{
    public function __construct(
        private ClassFileParser $classFileParser,
    ) {}

    /**
     * Discover driver factories from the given module manifests.
     *
     * @param array<ModuleManifest> $modules
     * @return array<string, class-string<FilesystemDriverFactoryInterface>>
     * @throws FilesystemException
     */
    public function discover(
        array $modules,
    ): array {
        $drivers = [];

        foreach ($modules as $manifest) {
            $srcDir = $manifest->path . '/src';

            if (!is_dir($srcDir)) {
                continue;
            }

            $drivers = array_merge($drivers, $this->discoverInDirectory($srcDir));
        }

        return $drivers;
    }

    /**
     * @return array<string, class-string<FilesystemDriverFactoryInterface>>
     * @throws FilesystemException
     */
    private function discoverInDirectory(
        string $directory,
    ): array {
        $drivers = [];

        foreach ($this->classFileParser->findPhpFiles($directory) as $file) {
            $filePath = $file->getPathname();
            $className = $this->classFileParser->extractClassName($filePath);

            if ($className === null) {
                continue;
            }

            if (!$this->classFileParser->loadClass($filePath, $className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);
            $attributes = $reflection->getAttributes(FilesystemDriver::class);

            if (count($attributes) === 0) {
                continue;
            }

            if (!$reflection->implementsInterface(FilesystemDriverFactoryInterface::class)) {
                throw new FilesystemException(
                    message: 'Filesystem driver factory must implement FilesystemDriverFactoryInterface',
                    context: "Class: $className",
                    suggestion: "Add 'implements FilesystemDriverFactoryInterface' to the class",
                );
            }

            $attribute = $attributes[0]->newInstance();
            $drivers[$attribute->name] = $className;
        }

        return $drivers;
    }
}
