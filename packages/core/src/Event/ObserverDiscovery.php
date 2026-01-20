<?php

declare(strict_types=1);

namespace Marko\Core\Event;

use Marko\Core\Attributes\Observer;
use Marko\Core\Exceptions\EventException;
use Marko\Core\Module\ModuleManifest;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

/**
 * Discovers observer classes in module src directories.
 */
class ObserverDiscovery
{
    /**
     * Discover observers from the given module manifests.
     *
     * @param array<ModuleManifest> $modules
     * @return array<ObserverDefinition>
     * @throws EventException When an observer class is missing the handle method
     */
    public function discover(array $modules): array
    {
        $observers = [];

        foreach ($modules as $manifest) {
            $srcDir = $manifest->path . '/src';

            if (!is_dir($srcDir)) {
                continue;
            }

            $observers = array_merge($observers, $this->discoverInDirectory($srcDir));
        }

        return $observers;
    }

    /**
     * @return array<ObserverDefinition>
     */
    private function discoverInDirectory(string $directory): array
    {
        $observers = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory),
        );

        foreach ($iterator as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $className = $this->extractClassName($file->getPathname());

            if ($className === null) {
                continue;
            }

            // Load the file so class is available for reflection
            require_once $file->getPathname();

            if (!class_exists($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);
            $attributes = $reflection->getAttributes(Observer::class);

            if (count($attributes) === 0) {
                continue;
            }

            // Validate that handle method exists
            if (!$reflection->hasMethod('handle')) {
                throw EventException::missingHandleMethod($className);
            }

            $attribute = $attributes[0]->newInstance();
            $observers[] = new ObserverDefinition(
                observerClass: $className,
                eventClass: $attribute->event,
                priority: $attribute->priority,
            );
        }

        return $observers;
    }

    private function extractClassName(string $filePath): ?string
    {
        $contents = file_get_contents($filePath);

        if ($contents === false) {
            return null;
        }

        $namespace = null;
        $class = null;

        // Extract namespace
        if (preg_match('/namespace\s+([^;]+);/', $contents, $matches)) {
            $namespace = $matches[1];
        }

        // Extract class name
        if (preg_match('/class\s+(\w+)/', $contents, $matches)) {
            $class = $matches[1];
        }

        if ($class === null) {
            return null;
        }

        return $namespace !== null ? $namespace . '\\' . $class : $class;
    }
}
