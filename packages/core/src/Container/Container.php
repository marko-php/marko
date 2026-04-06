<?php

declare(strict_types=1);

namespace Marko\Core\Container;

use Closure;
use Marko\Core\Exceptions\BindingException;
use Marko\Core\Exceptions\PluginException;
use Marko\Core\Plugin\PluginInterceptor;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionNamedType;

class Container implements ContainerInterface
{
    /** @var array<string, bool> */
    private array $shared = [];

    /** @var array<string, object> */
    private array $instances = [];

    /** @var array<string, string|Closure> */
    private array $bindings = [];

    private ?PluginInterceptor $pluginInterceptor = null;

    public function __construct(
        private readonly ?PreferenceRegistry $preferenceRegistry = null,
    ) {}

    public function setPluginInterceptor(PluginInterceptor $interceptor): void
    {
        $this->pluginInterceptor = $interceptor;
    }

    public function bind(
        string $interface,
        string|Closure $implementation,
    ): void {
        $this->bindings[$interface] = $implementation;
    }

    /**
     * @throws BindingException|ReflectionException|PluginException
     */
    public function get(
        string $id,
    ): mixed {
        return $this->resolve($id);
    }

    public function has(
        string $id,
    ): bool {
        return isset($this->bindings[$id]) || class_exists($id);
    }

    public function singleton(
        string $id,
    ): void {
        $this->shared[$id] = true;
    }

    /**
     * Register a pre-built instance for an interface or class.
     */
    public function instance(
        string $id,
        object $instance,
    ): void {
        $this->instances[$id] = $instance;
    }

    /**
     * @throws BindingException|ReflectionException|PluginException
     */
    public function call(Closure $callable): mixed
    {
        $reflection = new ReflectionFunction($callable);
        $parameters = $reflection->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                    continue;
                }
                throw BindingException::unresolvableCallableParameter($parameter->getName());
            }

            $typeName = $type->getName();

            if ($type->allowsNull() && !$this->has($typeName)) {
                $dependencies[] = null;
                continue;
            }

            $dependencies[] = $this->resolve($typeName);
        }

        return $callable(...$dependencies);
    }

    /**
     * @throws BindingException|ReflectionException|PluginException
     */
    private function resolve(
        string $id,
    ): object {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        $originalId = $id;

        // Check if there's a preference for this class (class → class replacement)
        if ($this->preferenceRegistry !== null) {
            $preference = $this->preferenceRegistry->getPreference($id);
            if ($preference !== null) {
                $id = $preference;
            }
        }

        // Check if there's a binding for this interface
        if (isset($this->bindings[$id])) {
            $binding = $this->bindings[$id];

            // Closure bindings are called with the container
            if ($binding instanceof Closure) {
                $instance = $binding($this);

                if ($this->pluginInterceptor !== null) {
                    $instance = $this->pluginInterceptor->createProxy($originalId, $id, $instance);
                }

                if (isset($this->shared[$originalId])) {
                    $this->instances[$originalId] = $instance;
                }

                return $instance;
            }

            $id = $binding;
        }

        if (interface_exists($id) && !class_exists($id)) {
            if (str_starts_with($id, 'Marko\\')) {
                $parts = explode('\\', $id);
                $segment = $parts[1] ?? '';
                $noDriverClass = "Marko\\$segment\\Exceptions\\NoDriverException";

                if (
                    $segment !== ''
                    && class_exists($noDriverClass)
                    && method_exists($noDriverClass, 'noDriverInstalled')
                ) {
                    throw $noDriverClass::noDriverInstalled();
                }
            }

            throw BindingException::noImplementation($id);
        }

        $reflectionClass = new ReflectionClass($id);
        $constructor = $reflectionClass->getConstructor();

        if ($constructor === null) {
            $instance = new $id();
        } else {
            $parameters = $constructor->getParameters();
            $dependencies = [];

            foreach ($parameters as $parameter) {
                $type = $parameter->getType();

                // Use default value for builtin types or untyped parameters
                if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                    if ($parameter->isDefaultValueAvailable()) {
                        $dependencies[] = $parameter->getDefaultValue();
                        continue;
                    }
                    throw BindingException::unresolvableParameter($parameter->getName(), $id);
                }

                $typeName = $type->getName();

                // Closure cannot be instantiated - use default value if available
                if ($typeName === 'Closure' || $typeName === Closure::class) {
                    if ($parameter->isDefaultValueAvailable()) {
                        $dependencies[] = $parameter->getDefaultValue();
                        continue;
                    }
                    throw BindingException::unresolvableParameter($parameter->getName(), $id);
                }

                $dependencies[] = $this->resolve($typeName);
            }

            $instance = $reflectionClass->newInstanceArgs($dependencies);
        }

        if ($this->pluginInterceptor !== null) {
            $instance = $this->pluginInterceptor->createProxy($originalId, $id, $instance);
        }

        if (isset($this->shared[$originalId])) {
            $this->instances[$originalId] = $instance;
        }

        return $instance;
    }
}
