<?php

declare(strict_types=1);

namespace Marko\Core\Container;

use Closure;
use Marko\Core\Exceptions\ArgumentException;
use Marko\Core\Exceptions\BindingException;
use Marko\Core\Exceptions\CircularDependencyException;
use Marko\Core\Exceptions\PluginException;
use Marko\Core\Exceptions\PreferenceConflictException;
use Marko\Core\Plugin\PluginInterceptor;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;

class Container implements ContainerInterface
{
    /** @var array<string, bool> */
    private array $shared = [];

    /** @var array<string, bool> */
    private array $resolving = [];

    /** @var array<string, object> */
    private array $instances = [];

    /** @var array<string, string|Closure> */
    private array $bindings = [];

    /** @var array<class-string, array<string, mixed>> */
    private array $arguments = [];

    private ?PluginInterceptor $pluginInterceptor = null;

    private readonly ArgumentResolver $argumentResolver;

    public function __construct(
        private readonly ?PreferenceRegistry $preferenceRegistry = null,
    ) {
        $this->argumentResolver = new ArgumentResolver();
    }

    /**
     * Configure constructor argument values for a class.
     *
     * @param class-string $class
     * @param array<string, mixed> $arguments
     */
    public function setArguments(
        string $class,
        array $arguments,
    ): void {
        $this->arguments[$class] = $arguments;
    }

    /**
     * Check every configured class without instantiating anything.
     *
     * @throws ArgumentException|BindingException|PreferenceConflictException|ReflectionException
     */
    public function validateArguments(): void
    {
        foreach ($this->arguments as $class => $arguments) {
            if ($arguments === []) {
                continue;
            }

            $this->validateConfiguredClass($class);
        }
    }

    /**
     * @param class-string $class
     * @throws ArgumentException|BindingException|PreferenceConflictException|ReflectionException
     */
    private function validateConfiguredClass(
        string $class,
    ): void {
        if (!class_exists($class) && !interface_exists($class)) {
            throw BindingException::classNotFound($class);
        }

        $reflection = new ReflectionClass($class);

        if ($reflection->isInterface()) {
            throw ArgumentException::interfaceKey($class);
        }

        $binding = $this->bindings[$class] ?? null;

        if ($binding instanceof Closure) {
            throw ArgumentException::closureBound($class);
        }

        if ($binding !== null && $binding !== $class) {
            throw ArgumentException::reboundClass($class, $binding);
        }

        $replacement = $this->preferenceRegistry?->getPreference($class);

        if ($reflection->isAbstract() && ($replacement === null || $replacement === $class)) {
            throw ArgumentException::abstractKey($class);
        }

        $this->validateClassArguments($reflection);

        if ($replacement !== null && $replacement !== $class) {
            $this->validateClassArguments(new ReflectionClass($replacement));
        }
    }

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
     * @throws ArgumentException|BindingException|CircularDependencyException|ReflectionException|PluginException|PreferenceConflictException
     */
    public function get(
        string $id,
    ): mixed {
        return $this->resolve($id);
    }

    public function has(
        string $id,
    ): bool {
        return isset($this->instances[$id]) || isset($this->bindings[$id]) || class_exists($id);
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
     * Instances already resolved, keyed by binding identifier. Never
     * triggers resolution — returns only what has already been built.
     * Pass an interface to return only instances implementing it.
     *
     * @return array<string, object>
     */
    public function resolvedInstances(?string $interface = null): array
    {
        if ($interface === null) {
            return $this->instances;
        }

        return array_filter(
            $this->instances,
            fn (object $instance): bool => $instance instanceof $interface,
        );
    }

    /**
     * @throws ArgumentException|BindingException|CircularDependencyException|ReflectionException|PluginException|PreferenceConflictException
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
     * @return array<string, mixed>
     * @throws PreferenceConflictException
     */
    private function effectiveArguments(
        string $class,
    ): array {
        $arguments = $this->arguments[$class] ?? [];

        if ($this->preferenceRegistry === null) {
            return $arguments;
        }

        foreach ($this->preferenceRegistry->replacedClasses($class) as $replaced) {
            $arguments = [...($this->arguments[$replaced] ?? []), ...$arguments];
        }

        return $arguments;
    }

    /**
     * Validate the class's own arguments, then each inherited entry against the class it came from.
     *
     * @param ReflectionClass<object> $class
     * @throws ArgumentException|PreferenceConflictException
     */
    private function validateClassArguments(
        ReflectionClass $class,
    ): void {
        $seen = $this->arguments[$class->getName()] ?? [];

        if ($seen !== []) {
            $this->argumentResolver->validate($class, $seen);
        }

        foreach ($this->preferenceRegistry?->replacedClasses($class->getName()) ?? [] as $replaced) {
            $inherited = array_diff_key($this->arguments[$replaced] ?? [], $seen);

            if ($inherited === []) {
                continue;
            }

            $this->argumentResolver->validate($class, $inherited, $replaced);
            $seen += $inherited;
        }
    }

    /**
     * @throws ArgumentException|BindingException|CircularDependencyException|ReflectionException|PluginException|PreferenceConflictException
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
                if ($this->effectiveArguments($id) !== []) {
                    throw ArgumentException::closureBound($id);
                }

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
                    // Pass the unresolved interface to factories that accept it, so the
                    // exception can tell a missing driver apart from a missing binding.
                    $acceptsInterface = new ReflectionMethod($noDriverClass, 'noDriverInstalled')
                        ->getNumberOfParameters() > 0;

                    throw $acceptsInterface
                        ? $noDriverClass::noDriverInstalled($id)
                        : $noDriverClass::noDriverInstalled();
                }
            }

            throw BindingException::noImplementation($id);
        }

        if (isset($this->resolving[$id])) {
            throw CircularDependencyException::forChain([...array_keys($this->resolving), $id]);
        }

        if (!class_exists($id)) {
            $requiredBy = array_key_last($this->resolving);

            throw BindingException::classNotFound($id, $requiredBy !== null ? (string) $requiredBy : null);
        }

        $reflectionClass = new ReflectionClass($id);
        $constructor = $reflectionClass->getConstructor();

        if ($constructor === null) {
            if ($this->effectiveArguments($id) !== []) {
                $this->validateClassArguments($reflectionClass);
            }

            $instance = new $id();
        } else {
            $this->resolving[$id] = true;

            try {
                $parameters = $constructor->getParameters();
                $dependencies = [];
                $arguments = $this->effectiveArguments($id);

                if ($arguments !== []) {
                    $this->validateClassArguments($reflectionClass);
                }

                foreach ($parameters as $parameter) {
                    if (array_key_exists($parameter->getName(), $arguments)) {
                        $dependencies[] = $this->argumentResolver->resolveValue(
                            $parameter,
                            $arguments[$parameter->getName()],
                            $id,
                            $this,
                        );
                        continue;
                    }

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
            } finally {
                unset($this->resolving[$id]);
            }
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
