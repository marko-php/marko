<?php

declare(strict_types=1);

namespace Marko\Core\Plugin;

use Closure;
use Marko\Core\Container\ContainerInterface;
use Psr\Container\ContainerExceptionInterface;
use ReflectionException;
use ReflectionMethod;

/**
 * Trait that implements the core before→target→after plugin chain logic.
 *
 * This trait is a deliberate exception to the "No Traits" code standard.
 * It exists because generated interceptor classes (both interface wrappers and
 * concrete subclasses) must share this logic, and a trait is the only viable
 * mechanism for sharing logic with eval-generated classes while keeping generated
 * code minimal.
 *
 * @codingStandardsIgnoreFile
 */
trait PluginInterception
{
    private object $pluginTarget;

    /** @var class-string */
    private string $pluginTargetClass;

    private ContainerInterface $pluginContainer;

    private PluginRegistry $pluginRegistry;

    /**
     * Initialize the interception state. Called after object construction by
     * the interceptor factory.
     *
     * @param class-string $pluginTargetClass
     */
    public function initInterception(
        object $pluginTarget,
        string $pluginTargetClass,
        ContainerInterface $pluginContainer,
        PluginRegistry $pluginRegistry,
    ): void {
        $this->pluginTarget = $pluginTarget;
        $this->pluginTargetClass = $pluginTargetClass;
        $this->pluginContainer = $pluginContainer;
        $this->pluginRegistry = $pluginRegistry;
    }

    /**
     * Get the underlying target instance.
     */
    public function getPluginTarget(): object
    {
        return $this->pluginTarget;
    }

    /**
     * Execute the plugin chain for a method call using the wrapper strategy.
     * Calls $this->pluginTarget->$method(...$arguments) as the target invocation.
     *
     * @throws PluginArgumentCountException|ContainerExceptionInterface|ReflectionException
     */
    protected function interceptCall(
        string $method,
        array $arguments,
    ): mixed {
        $arguments = $this->runBeforePlugins($method, $arguments, $shortCircuit);

        if ($shortCircuit !== null) {
            return $shortCircuit;
        }

        $result = $this->pluginTarget->$method(...$arguments);

        return $this->runAfterPlugins($method, $arguments, $result);
    }

    /**
     * Execute the plugin chain for a method call using the subclass strategy.
     * Calls $parentCall(...$arguments) as the target invocation.
     *
     * @throws PluginArgumentCountException|ContainerExceptionInterface|ReflectionException
     */
    protected function interceptParentCall(
        string $method,
        array $arguments,
        Closure $parentCall,
    ): mixed {
        $arguments = $this->runBeforePlugins($method, $arguments, $shortCircuit);

        if ($shortCircuit !== null) {
            return $shortCircuit;
        }

        $result = $parentCall(...$arguments);

        return $this->runAfterPlugins($method, $arguments, $result);
    }

    /**
     * Run before plugins and return (potentially modified) arguments.
     * Sets $shortCircuit to the early return value when a before plugin short-circuits.
     *
     * @throws PluginArgumentCountException|ContainerExceptionInterface|ReflectionException
     */
    private function runBeforePlugins(
        string $method,
        array $arguments,
        mixed &$shortCircuit,
    ): array {
        $shortCircuit = null;
        $beforeMethods = $this->pluginRegistry->getBeforeMethodsFor($this->pluginTargetClass, $method);

        foreach ($beforeMethods as $beforeMethod) {
            $plugin = $this->pluginContainer->get($beforeMethod['pluginClass']);
            $result = $plugin->{$beforeMethod['method']}(...$arguments);

            if (is_array($result)) {
                $expectedCount = (new ReflectionMethod($this->pluginTarget, $method))->getNumberOfParameters();
                if (count($result) !== $expectedCount) {
                    throw PluginArgumentCountException::wrongCount(
                        pluginClass: $beforeMethod['pluginClass'],
                        targetClass: $this->pluginTargetClass,
                        targetMethod: $method,
                        expectedCount: $expectedCount,
                        actualCount: count($result),
                    );
                }
                $arguments = $result;
            } elseif ($result !== null) {
                $shortCircuit = $result;

                return $arguments;
            }
        }

        return $arguments;
    }

    /**
     * Run after plugins and return the final result.
     *
     * @throws ContainerExceptionInterface
     */
    private function runAfterPlugins(
        string $method,
        array $arguments,
        mixed $result,
    ): mixed {
        $afterMethods = $this->pluginRegistry->getAfterMethodsFor($this->pluginTargetClass, $method);

        foreach ($afterMethods as $afterMethod) {
            $plugin = $this->pluginContainer->get($afterMethod['pluginClass']);
            $result = $plugin->{$afterMethod['method']}($result, ...$arguments);
        }

        return $result;
    }
}
