<?php

declare(strict_types=1);

namespace Marko\Core\Plugin;

class PluginRegistry
{
    /**
     * @var array<class-string, array<PluginDefinition>>
     */
    private array $plugins = [];

    /**
     * Register a plugin definition.
     */
    public function register(
        PluginDefinition $plugin,
    ): void {
        $targetClass = $plugin->targetClass;

        if (!isset($this->plugins[$targetClass])) {
            $this->plugins[$targetClass] = [];
        }

        $this->plugins[$targetClass][] = $plugin;
    }

    /**
     * Check if any plugins are registered for a given target class.
     *
     * @param class-string $targetClass
     */
    public function hasPluginsFor(
        string $targetClass,
    ): bool {
        return isset($this->plugins[$targetClass]) && count($this->plugins[$targetClass]) > 0;
    }

    /**
     * Get all plugins for a given target class.
     *
     * @param class-string $targetClass
     * @return array<PluginDefinition>
     */
    public function getPluginsFor(
        string $targetClass,
    ): array {
        return $this->plugins[$targetClass] ?? [];
    }

    /**
     * Get sorted before methods for a specific target method.
     *
     * @param class-string $targetClass
     * @return array<array{pluginClass: class-string, method: string, sortOrder: int}>
     */
    public function getBeforeMethodsFor(
        string $targetClass,
        string $targetMethod,
    ): array {
        return $this->getSortedMethodsFor($targetClass, $targetMethod, 'before');
    }

    /**
     * Get sorted after methods for a specific target method.
     *
     * @param class-string $targetClass
     * @return array<array{pluginClass: class-string, method: string, sortOrder: int}>
     */
    public function getAfterMethodsFor(
        string $targetClass,
        string $targetMethod,
    ): array {
        return $this->getSortedMethodsFor($targetClass, $targetMethod, 'after');
    }

    /**
     * Get sorted plugin methods for a specific target method.
     *
     * @param string $targetClass
     * @param string $targetMethod
     * @param 'before'|'after' $type
     * @return array<array{pluginClass: class-string, method: string, sortOrder: int}>
     */
    private function getSortedMethodsFor(
        string $targetClass,
        string $targetMethod,
        string $type,
    ): array {
        $plugins = $this->getPluginsFor($targetClass);
        $methods = [];

        $methodsProperty = $type === 'before' ? 'beforeMethods' : 'afterMethods';

        foreach ($plugins as $plugin) {
            foreach ($plugin->$methodsProperty as $methodName => $sortOrder) {
                // Check if the plugin method matches the target method pattern
                // Convention: beforeDoAction targets doAction, afterDoAction targets doAction
                $expectedMethodName = $type . ucfirst($targetMethod);
                if ($methodName === $expectedMethodName) {
                    $methods[] = [
                        'pluginClass' => $plugin->pluginClass,
                        'method' => $methodName,
                        'sortOrder' => $sortOrder,
                    ];
                }
            }
        }

        // Sort by sortOrder (lower values run first)
        usort($methods, fn (array $a, array $b) => $a['sortOrder'] <=> $b['sortOrder']);

        return $methods;
    }
}
