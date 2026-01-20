<?php

declare(strict_types=1);

namespace Marko\Core\Container;

use Marko\Core\Exceptions\BindingException;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;

class Container implements ContainerInterface
{
    /** @var array<string, bool> */
    private array $shared = [];

    /** @var array<string, object> */
    private array $instances = [];

    /** @var array<string, string> */
    private array $bindings = [];

    public function __construct(
        private readonly ?PreferenceRegistry $preferenceRegistry = null,
    ) {}

    public function bind(
        string $interface,
        string $implementation,
    ): void {
        $this->bindings[$interface] = $implementation;
    }

    /**
     * @throws BindingException|ReflectionException
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
     * @throws BindingException|ReflectionException
     */
    private function resolve(
        string $id,
    ): object {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // Check if there's a preference for this class (class → class replacement)
        if ($this->preferenceRegistry !== null) {
            $preference = $this->preferenceRegistry->getPreference($id);
            if ($preference !== null) {
                $id = $preference;
            }
        }

        // Check if there's a binding for this interface
        if (isset($this->bindings[$id])) {
            $id = $this->bindings[$id];
        }

        if (interface_exists($id) && !class_exists($id)) {
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

                if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                    throw BindingException::unresolvableParameter($parameter->getName(), $id);
                }

                $dependencies[] = $this->resolve($type->getName());
            }

            $instance = $reflectionClass->newInstanceArgs($dependencies);
        }

        if (isset($this->shared[$id])) {
            $this->instances[$id] = $instance;
        }

        return $instance;
    }
}
