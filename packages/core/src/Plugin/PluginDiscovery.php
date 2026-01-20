<?php

declare(strict_types=1);

namespace Marko\Core\Plugin;

use Marko\Core\Attributes\After;
use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;
use Marko\Core\Exceptions\PluginException;
use Marko\Core\Module\ModuleManifest;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionException;
use RegexIterator;

class PluginDiscovery
{
    /**
     * Discover plugin files in a module's src directory.
     *
     * @return array<string> List of absolute paths to PHP files containing plugins
     */
    public function discoverInModule(
        ModuleManifest $manifest,
    ): array {
        $srcDir = $manifest->path . '/src';

        if (!is_dir($srcDir)) {
            return [];
        }

        $pluginFiles = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($srcDir),
        );
        $phpFiles = new RegexIterator($iterator, '/\.php$/');

        foreach ($phpFiles as $file) {
            $content = file_get_contents($file->getPathname());
            if ($content !== false && str_contains($content, '#[Plugin')) {
                $pluginFiles[] = $file->getPathname();
            }
        }

        return $pluginFiles;
    }

    /**
     * Parse a plugin class and extract its definition.
     *
     * @param class-string $pluginClass
     * @throws PluginException|ReflectionException
     */
    public function parsePluginClass(
        string $pluginClass,
    ): PluginDefinition {
        $reflection = new ReflectionClass($pluginClass);
        $pluginAttributes = $reflection->getAttributes(Plugin::class);

        if (empty($pluginAttributes)) {
            throw PluginException::missingPluginAttribute($pluginClass);
        }

        $pluginAttribute = $pluginAttributes[0]->newInstance();

        $beforeMethods = [];
        $afterMethods = [];
        foreach ($reflection->getMethods() as $method) {
            $beforeAttributes = $method->getAttributes(Before::class);
            if (!empty($beforeAttributes)) {
                $beforeAttribute = $beforeAttributes[0]->newInstance();
                $beforeMethods[$method->getName()] = $beforeAttribute->sortOrder;
            }

            $afterAttributes = $method->getAttributes(After::class);
            if (!empty($afterAttributes)) {
                $afterAttribute = $afterAttributes[0]->newInstance();
                $afterMethods[$method->getName()] = $afterAttribute->sortOrder;
            }
        }

        return new PluginDefinition(
            pluginClass: $pluginClass,
            targetClass: $pluginAttribute->target,
            beforeMethods: $beforeMethods,
            afterMethods: $afterMethods,
        );
    }

    /**
     * Validate that a class with #[Before]/#[After] methods also has #[Plugin] attribute.
     *
     * @param class-string $className
     * @throws PluginException|ReflectionException
     */
    public function validatePluginMethods(
        string $className,
    ): void {
        $reflection = new ReflectionClass($className);
        $hasPluginAttribute = !empty($reflection->getAttributes(Plugin::class));

        if ($hasPluginAttribute) {
            return;
        }

        $orphanedMethods = [];
        foreach ($reflection->getMethods() as $method) {
            $hasBeforeAttribute = !empty($method->getAttributes(Before::class));
            $hasAfterAttribute = !empty($method->getAttributes(After::class));

            if ($hasBeforeAttribute || $hasAfterAttribute) {
                $orphanedMethods[] = $method->getName();
            }
        }

        if (!empty($orphanedMethods)) {
            throw PluginException::orphanedPluginMethods($className, $orphanedMethods);
        }
    }
}
