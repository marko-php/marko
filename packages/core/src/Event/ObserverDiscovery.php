<?php

declare(strict_types=1);

namespace Marko\Core\Event;

use Error;
use Marko\Core\Attributes\Observer;
use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Exceptions\EventException;
use Marko\Core\Exceptions\MarkoException;
use Marko\Core\Module\ModuleManifest;
use ReflectionClass;

/**
 * Discovers observer classes in module src directories.
 */
readonly class ObserverDiscovery
{
    public function __construct(
        private ClassFileParser $classFileParser,
    ) {}

    /**
     * Discover observers from the given module manifests.
     *
     * @param array<ModuleManifest> $modules
     * @return array<ObserverDefinition>
     * @throws EventException When an observer class is missing the handle method
     */
    public function discover(
        array $modules,
    ): array {
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
     * @throws EventException
     */
    private function discoverInDirectory(
        string $directory,
    ): array {
        $observers = [];

        foreach ($this->classFileParser->findPhpFiles($directory) as $file) {
            $filePath = $file->getPathname();
            $className = $this->classFileParser->extractClassName($filePath);

            if ($className === null) {
                continue;
            }

            // Load the file so class is available for reflection
            try {
                require_once $filePath;
            } catch (Error $e) {
                $missingClass = MarkoException::extractMissingClass($e);
                if ($missingClass !== null) {
                    throw EventException::classNotFoundDuringDiscovery($filePath, $missingClass, $e);
                }
                throw $e;
            }

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
                async: $attribute->async,
            );
        }

        return $observers;
    }
}
