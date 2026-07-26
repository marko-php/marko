<?php

declare(strict_types=1);

namespace Marko\Core\Plugin;

use Marko\Core\Container\ContainerInterface;

interface PluginInterceptedInterface
{
    /**
     * Initialize the interception state. Interceptor classes are constructed without
     * arguments (interface wrappers) or without a constructor at all (concrete
     * subclasses), so PluginInterceptor calls this immediately after construction.
     *
     * Implemented by the PluginInterception trait, which every interceptor uses.
     *
     * @param class-string $pluginTargetClass
     */
    public function initInterception(
        object $pluginTarget,
        string $pluginTargetClass,
        ContainerInterface $pluginContainer,
        PluginRegistry $pluginRegistry,
    ): void;

    /**
     * Get the underlying target instance that this interceptor wraps.
     */
    public function getPluginTarget(): object;
}
