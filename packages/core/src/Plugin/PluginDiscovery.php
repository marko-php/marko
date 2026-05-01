<?php

declare(strict_types=1);

namespace Marko\Core\Plugin;

use Marko\Core\Attributes\After;
use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;
use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Exceptions\PluginException;
use Marko\Core\Module\ModuleManifest;
use ReflectionClass;
use ReflectionException;

class PluginDiscovery
{
    /**
     * Discover plugin definitions in a module's src directory.
     *
     * Scans PHP files for classes with the #[Plugin] attribute and returns
     * fully parsed PluginDefinition instances ready for registration.
     *
     * @return array<PluginDefinition>
     * @throws PluginException|ReflectionException
     */
    public function discoverInModule(ModuleManifest $manifest): array
    {
        $srcDir = $manifest->path . '/src';

        if (!is_dir($srcDir)) {
            return [];
        }

        $definitions = [];
        $parser = new ClassFileParser();

        foreach ($parser->findPhpFiles($srcDir) as $file) {
            $filepath = $file->getPathname();

            // Cheap pre-filter: skip files that obviously don't declare a plugin
            // before paying for class extraction, autoload, and reflection.
            $contents = file_get_contents($filepath);
            if ($contents === false || !str_contains($contents, '#[Plugin')) {
                continue;
            }

            $className = $parser->extractClassName($filepath);
            if ($className === null) {
                continue;
            }

            if (!$parser->loadClass($filepath, $className)) {
                continue;
            }

            $reflector = new ReflectionClass($className);
            $pluginAttributes = $reflector->getAttributes(Plugin::class);

            if (empty($pluginAttributes)) {
                continue;
            }

            $definitions[] = $this->parsePluginClass($className);
        }

        return $definitions;
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
        /** @var array<string, list<string>> $beforeTargets */
        $beforeTargets = [];
        /** @var array<string, list<string>> $afterTargets */
        $afterTargets = [];

        foreach ($reflection->getMethods() as $method) {
            $pluginMethodName = $method->getName();

            $beforeAttributes = $method->getAttributes(Before::class);
            if (!empty($beforeAttributes)) {
                $beforeAttribute = $beforeAttributes[0]->newInstance();
                $targetMethodName = $beforeAttribute->method ?? $pluginMethodName;
                $beforeTargets[$targetMethodName][] = $pluginMethodName;
                $beforeMethods[$targetMethodName] = [
                    'pluginMethod' => $pluginMethodName,
                    'sortOrder' => $beforeAttribute->sortOrder,
                ];
            }

            $afterAttributes = $method->getAttributes(After::class);
            if (!empty($afterAttributes)) {
                $afterAttribute = $afterAttributes[0]->newInstance();
                $targetMethodName = $afterAttribute->method ?? $pluginMethodName;
                $afterTargets[$targetMethodName][] = $pluginMethodName;
                $afterMethods[$targetMethodName] = [
                    'pluginMethod' => $pluginMethodName,
                    'sortOrder' => $afterAttribute->sortOrder,
                ];
            }
        }

        foreach ($beforeTargets as $targetMethodName => $pluginMethods) {
            if (count($pluginMethods) > 1) {
                throw PluginException::duplicatePluginHook($pluginClass, 'before', $targetMethodName, $pluginMethods);
            }
        }

        foreach ($afterTargets as $targetMethodName => $pluginMethods) {
            if (count($pluginMethods) > 1) {
                throw PluginException::duplicatePluginHook($pluginClass, 'after', $targetMethodName, $pluginMethods);
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
