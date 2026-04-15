<?php

declare(strict_types=1);

namespace Marko\Core\Plugin;

use Marko\Core\Container\ContainerInterface;
use Marko\Core\Exceptions\PluginException;
use ReflectionException;

readonly class PluginInterceptor
{
    public function __construct(
        private ContainerInterface $container,
        private PluginRegistry $registry,
        private InterceptorClassGenerator $generator,
    ) {}

    /**
     * Create a proxy for a target that intercepts method calls via plugins.
     *
     * Returns the original $target unchanged when no plugins are applicable.
     *
     * @param string $originalId  The interface or class originally requested
     *                            (e.g. HasherInterface::class).
     * @param string $resolvedId  The concrete class after container resolution
     *                            (e.g. BcryptHasher::class).
     * @param object $target      The already-constructed instance to wrap.
     * @return object             Either $target unchanged or a generated interceptor.
     * @throws PluginException|ReflectionException
     */
    public function createProxy(
        string $originalId,
        string $resolvedId,
        object $target,
    ): object {
        // --- 1. Determine whether any plugins apply --------------------------

        $originalSameAsResolved = $originalId === $resolvedId;

        if ($originalSameAsResolved) {
            if (!$this->registry->hasPluginsForClassOrInterfaces($resolvedId)) {
                return $target;
            }
        } else {
            $hasOriginal = $this->registry->hasPluginsFor($originalId);
            $hasResolved = $this->registry->hasPluginsForClassOrInterfaces($resolvedId);

            if (!$hasOriginal && !$hasResolved) {
                return $target;
            }
        }

        // --- 2. Choose strategy and generate interceptor class ---------------

        // Interface wrapper: $originalId is an interface with plugins.
        if (interface_exists($originalId) && $this->registry->hasPluginsFor($originalId)) {
            $className = $this->generator->generateInterfaceWrapper(
                $originalId,
                $this->registry,
                $this->container,
            );

            /** @var PluginInterceptedInterface&object $instance */
            $instance = new $className();
            $instance->initInterception($target, $originalId, $this->container, $this->registry);

            return $instance;
        }

        // Fall back: check the concrete class and its interfaces.
        $effectiveTarget = $this->registry->getEffectiveTargetClass($resolvedId);

        if ($effectiveTarget !== null && interface_exists($effectiveTarget)) {
            // Effective target is an interface → interface wrapper strategy.
            $className = $this->generator->generateInterfaceWrapper(
                $effectiveTarget,
                $this->registry,
                $this->container,
            );

            /** @var PluginInterceptedInterface&object $instance */
            $instance = new $className();
            $instance->initInterception($target, $effectiveTarget, $this->container, $this->registry);

            return $instance;
        }

        // Concrete subclass strategy (may throw for readonly classes).
        $className = $this->generator->generateConcreteSubclass(
            $resolvedId,
            $this->registry,
            $this->container,
        );

        /** @var PluginInterceptedInterface&object $instance */
        $instance = new $className();
        $instance->initInterception($target, $resolvedId, $this->container, $this->registry);

        return $instance;
    }
}
