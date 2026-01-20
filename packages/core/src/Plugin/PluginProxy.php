<?php

declare(strict_types=1);

namespace Marko\Core\Plugin;

use Marko\Core\Container\ContainerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

readonly class PluginProxy
{
    /**
     * @param object $target The target instance to wrap
     * @param class-string $targetClass The target class name
     */
    public function __construct(
        private object $target,
        private string $targetClass,
        private ContainerInterface $container,
        private PluginRegistry $registry,
    ) {}

    /**
     * Intercept method calls and apply plugins.
     *
     * @param string $method
     * @param array $arguments
     * @return mixed
     * @throws ContainerExceptionInterface|NotFoundExceptionInterface
     */
    public function __call(
        string $method,
        array $arguments,
    ): mixed {
        // Execute before plugins in sort order
        $beforeMethods = $this->registry->getBeforeMethodsFor($this->targetClass, $method);
        foreach ($beforeMethods as $beforeMethod) {
            $plugin = $this->container->get($beforeMethod['pluginClass']);
            $result = $plugin->{$beforeMethod['method']}(...$arguments);

            // Short-circuit if before plugin returns non-null
            if ($result !== null) {
                return $result;
            }
        }

        // Execute the target method
        $result = $this->target->$method(...$arguments);

        // Execute after plugins in sort order
        $afterMethods = $this->registry->getAfterMethodsFor($this->targetClass, $method);
        foreach ($afterMethods as $afterMethod) {
            $plugin = $this->container->get($afterMethod['pluginClass']);
            $result = $plugin->{$afterMethod['method']}($result, ...$arguments);
        }

        return $result;
    }
}
