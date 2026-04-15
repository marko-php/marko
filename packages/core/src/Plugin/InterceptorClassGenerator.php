<?php

declare(strict_types=1);

namespace Marko\Core\Plugin;

use Marko\Core\Exceptions\PluginException;
use ReflectionClass;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

class InterceptorClassGenerator
{
    /**
     * @var array<string, string> Cache of target class => generated class name
     */
    private array $cache = [];

    /**
     * Generate and eval an interface wrapper class, returning the generated class name.
     *
     * @param class-string $interfaceName
     * @throws PluginException|ReflectionException
     */
    public function generateInterfaceWrapper(
        string $interfaceName,
        PluginRegistry $pluginRegistry,
    ): string {
        $cacheKey = 'wrapper:' . $interfaceName;

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $code = $this->generateInterfaceWrapperCode($interfaceName, $pluginRegistry);
        $className = $this->extractClassName($code);

        if (!class_exists($className)) {
            $this->loadCode($code);
        }

        $this->cache[$cacheKey] = $className;

        return $className;
    }

    /**
     * Generate the PHP code string for an interface wrapper class.
     *
     * @param class-string $interfaceName
     * @throws ReflectionException
     */
    public function generateInterfaceWrapperCode(
        string $interfaceName,
        PluginRegistry $pluginRegistry,
    ): string {
        $className = $this->buildClassName($interfaceName);
        $reflection = new ReflectionClass($interfaceName);

        $methods = $this->collectInterfaceMethods($reflection);
        $methodCode = implode("\n\n", array_map(
            fn (ReflectionMethod $m) => $this->generateWrapperMethod($m),
            $methods,
        ));

        return <<<PHP
        class $className implements \\$interfaceName, \\Marko\\Core\\Plugin\\PluginInterceptedInterface
        {
            use \\Marko\\Core\\Plugin\\PluginInterception;

        $methodCode
        }
        PHP;
    }

    /**
     * Generate and eval a concrete subclass, returning the generated class name.
     *
     * @param class-string $targetClass
     * @throws PluginException|ReflectionException
     */
    public function generateConcreteSubclass(
        string $targetClass,
        PluginRegistry $pluginRegistry,
    ): string {
        $pluggedMethods = $this->resolvePluggedMethods($targetClass, $pluginRegistry);
        sort($pluggedMethods);
        $cacheKey = 'subclass:' . $targetClass . ':' . implode(',', $pluggedMethods);

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $code = $this->generateConcreteSubclassCode($targetClass, $pluginRegistry);
        $className = $this->extractClassName($code);

        if (!class_exists($className)) {
            $this->loadCode($code);
        }

        $this->cache[$cacheKey] = $className;

        return $className;
    }

    /**
     * Generate the PHP code string for a concrete subclass.
     *
     * @param class-string $targetClass
     * @throws PluginException|ReflectionException
     */
    public function generateConcreteSubclassCode(
        string $targetClass,
        PluginRegistry $pluginRegistry,
    ): string {
        $reflection = new ReflectionClass($targetClass);

        if ($reflection->isReadOnly()) {
            throw PluginException::cannotInterceptReadonly($targetClass);
        }

        $pluggedMethods = $this->resolvePluggedMethods($targetClass, $pluginRegistry);
        sort($pluggedMethods);
        $methodsHash = substr(md5(implode(',', $pluggedMethods)), 0, 8);
        $className = $this->buildClassName($targetClass) . '_' . $methodsHash;

        $methodCode = implode("\n\n", array_map(
            fn (ReflectionMethod $m) => $this->generateSubclassMethod($m),
            array_filter(
                $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
                fn (ReflectionMethod $m) => in_array($m->getName(), $pluggedMethods, true)
                    && !$m->isConstructor()
                    && !$m->isStatic(),
            ),
        ));

        return <<<PHP
        class $className extends \\$targetClass implements \\Marko\\Core\\Plugin\\PluginInterceptedInterface
        {
            use \\Marko\\Core\\Plugin\\PluginInterception;

        $methodCode
        }
        PHP;
    }

    /**
     * Load generated PHP code via eval.
     */
    private function loadCode(string $code): void
    {
        eval($code);
    }

    /**
     * Build a deterministic class name for the given target.
     */
    private function buildClassName(string $targetClass): string
    {
        $sanitized = str_replace('\\', '_', $targetClass);
        $hash = substr(md5($targetClass), 0, 8);

        return "Marko_Interceptor_{$sanitized}_$hash";
    }

    /**
     * Extract the class name from a generated code snippet.
     */
    private function extractClassName(string $code): string
    {
        preg_match('/class (Marko_Interceptor_\S+)\s/', $code, $matches);

        return $matches[1];
    }

    /**
     * Collect all methods from a reflected interface, including inherited ones.
     *
     * @return array<ReflectionMethod>
     */
    private function collectInterfaceMethods(ReflectionClass $reflection): array
    {
        return array_filter(
            $reflection->getMethods(),
            fn (ReflectionMethod $m) => !$m->isConstructor(),
        );
    }

    /**
     * Generate a single method stub for the interface wrapper strategy.
     */
    private function generateWrapperMethod(ReflectionMethod $method): string
    {
        $name = $method->getName();
        $params = $this->renderParameters($method);
        $returnType = $this->renderReturnType($method);
        $args = $this->renderArgumentsArray($method);

        $isVoid = $this->isVoidReturn($method);
        $body = $isVoid
            ? "\$this->interceptCall('$name', $args);"
            : "return \$this->interceptCall('$name', $args);";

        return <<<PHP
                public function $name($params)$returnType
                {
                    $body
                }
        PHP;
    }

    /**
     * Generate a single overridden method for the concrete subclass strategy.
     */
    private function generateSubclassMethod(ReflectionMethod $method): string
    {
        $name = $method->getName();
        $params = $this->renderParameters($method);
        $returnType = $this->renderReturnType($method);
        $args = $this->renderArgumentsArray($method);

        $isVoid = $this->isVoidReturn($method);
        $body = $isVoid
            ? "\$this->interceptParentCall('$name', $args, fn(...\$_args) => parent::$name(...\$_args));"
            : "return \$this->interceptParentCall('$name', $args, fn(...\$_args) => parent::$name(...\$_args));";

        return <<<PHP
                public function $name($params)$returnType
                {
                    $body
                }
        PHP;
    }

    /**
     * Render method parameters as a string for a method signature.
     */
    private function renderParameters(ReflectionMethod $method): string
    {
        return implode(', ', array_map(
            fn (ReflectionParameter $p) => $this->renderParameter($p),
            $method->getParameters(),
        ));
    }

    /**
     * Render a single parameter declaration.
     */
    private function renderParameter(ReflectionParameter $param): string
    {
        $parts = [];

        if ($param->hasType()) {
            $parts[] = $this->renderType($param->getType());
        }

        $variadicPrefix = $param->isVariadic() ? '...' : '';
        $parts[] = $variadicPrefix . '$' . $param->getName();

        if (!$param->isVariadic() && $param->isOptional() && $param->isDefaultValueAvailable()) {
            $parts[] = '= ' . $this->renderDefaultValue($param);
        }

        return implode(' ', $parts);
    }

    /**
     * Render the return type annotation string (e.g. ": string").
     */
    private function renderReturnType(ReflectionMethod $method): string
    {
        if (!$method->hasReturnType()) {
            return '';
        }

        return ': ' . $this->renderType($method->getReturnType());
    }

    /**
     * Render a ReflectionType to its string representation.
     */
    private function renderType(
        ReflectionNamedType|ReflectionUnionType|ReflectionIntersectionType|ReflectionType|null $type,
    ): string {
        if ($type === null) {
            return '';
        }

        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();

            if (!$type->isBuiltin() && $name !== 'self' && $name !== 'static' && $name !== 'parent') {
                $name = '\\' . $name;
            }

            return ($type->allowsNull() && $name !== 'null' && $name !== 'mixed') ? '?' . $name : $name;
        }

        if ($type instanceof ReflectionUnionType) {
            return implode('|', array_map(
                fn ($t) => $this->renderType($t),
                $type->getTypes(),
            ));
        }

        if ($type instanceof ReflectionIntersectionType) {
            return implode('&', array_map(
                fn ($t) => $this->renderType($t),
                $type->getTypes(),
            ));
        }

        return (string) $type;
    }

    /**
     * Render the default value of a parameter as a PHP literal.
     */
    private function renderDefaultValue(ReflectionParameter $param): string
    {
        if ($param->isDefaultValueConstant()) {
            $constName = $param->getDefaultValueConstantName();

            return $constName !== null ? '\\' . $constName : 'null';
        }

        $value = $param->getDefaultValue();

        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_string($value) => "'" . addslashes($value) . "'",
            is_array($value) => '[]',
            default => (string) $value,
        };
    }

    /**
     * Render the arguments array literal passed to interceptCall / interceptParentCall.
     */
    private function renderArgumentsArray(ReflectionMethod $method): string
    {
        $params = $method->getParameters();

        if ($params === []) {
            return '[]';
        }

        // For variadic, use spread; otherwise named array
        $hasVariadic = array_any($params, fn (ReflectionParameter $p) => $p->isVariadic());

        if ($hasVariadic) {
            $nonVariadic = array_filter($params, fn (ReflectionParameter $p) => !$p->isVariadic());
            $variadicParam = array_find($params, fn (ReflectionParameter $p) => $p->isVariadic());

            $parts = array_map(
                fn (ReflectionParameter $p) => '$' . $p->getName(),
                $nonVariadic,
            );
            $parts[] = '...$' . $variadicParam->getName();

            return '[' . implode(', ', $parts) . ']';
        }

        $parts = array_map(
            fn (ReflectionParameter $p) => '$' . $p->getName(),
            $params,
        );

        return '[' . implode(', ', $parts) . ']';
    }

    /**
     * Check if a method has a void return type.
     */
    private function isVoidReturn(ReflectionMethod $method): bool
    {
        if (!$method->hasReturnType()) {
            return false;
        }

        $type = $method->getReturnType();

        return $type instanceof ReflectionNamedType && $type->getName() === 'void';
    }

    /**
     * Resolve which method names have registered plugins for the given target class.
     *
     * @param class-string $targetClass
     * @return array<string>
     */
    private function resolvePluggedMethods(
        string $targetClass,
        PluginRegistry $pluginRegistry,
    ): array {
        $plugins = $pluginRegistry->getPluginsFor($targetClass);
        $methods = [];

        foreach ($plugins as $plugin) {
            foreach (array_keys($plugin->beforeMethods) as $method) {
                $methods[] = $method;
            }

            foreach (array_keys($plugin->afterMethods) as $method) {
                $methods[] = $method;
            }
        }

        return array_unique($methods);
    }
}
