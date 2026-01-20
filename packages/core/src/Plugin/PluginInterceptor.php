<?php

declare(strict_types=1);

namespace Marko\Core\Plugin;

use Marko\Core\Container\ContainerInterface;

readonly class PluginInterceptor
{
    public function __construct(
        private ContainerInterface $container,
        private PluginRegistry $registry,
    ) {}

    /**
     * Create a proxy for a target class that intercepts method calls.
     *
     * Returns the original target if no plugins are registered for the class.
     *
     * @template T of object
     * @param class-string<T> $targetClass
     * @param T $target
     * @return T
     */
    public function createProxy(
        string $targetClass,
        object $target,
    ): object {
        // Only create proxy if there are plugins for this class
        if (!$this->registry->hasPluginsFor($targetClass)) {
            return $target;
        }

        return new PluginProxy(
            target: $target,
            targetClass: $targetClass,
            container: $this->container,
            registry: $this->registry,
        );
    }
}
