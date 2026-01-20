<?php

declare(strict_types=1);

namespace Marko\Core\Container;

use Marko\Core\Exceptions\BindingException;
use ReflectionClass;

class Container implements ContainerInterface
{
    /** @var array<string, bool> */
    private array $shared = [];

    /** @var array<string, object> */
    private array $instances = [];

    public function get(string $id): mixed
    {
        return $this->resolve($id);
    }

    public function has(string $id): bool
    {
        return class_exists($id);
    }

    public function singleton(string $id): void
    {
        $this->shared[$id] = true;
    }

    private function resolve(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
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
