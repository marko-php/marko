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
use UnitEnum;

/**
 * Generates the classes that put plugins around a service.
 *
 * An interface wrapper implements the plugged interface and intercepts its methods. Given the
 * concrete class behind it, the wrapper also implements that class's other interfaces, so the
 * proxy still satisfies type checks against them; their methods call the target directly and
 * bypass plugins, and a method the plugged interface also declares stays intercepted.
 *
 * An other interface is left out when it, or a parent the plugged interface does not cover, is
 * internal to PHP or has plugins of its own; when it declares a property (its own or inherited);
 * when one of its methods is static, returns by
 * reference, uses a reserved name, returns static or has an object default; or when a method
 * signature or constant clashes with one already emitted (plugged interface first, then the
 * other interfaces by name). Leaving one out keeps the behaviour from before other interfaces
 * were kept. A declaration the wrapper cannot reproduce would be a fatal error in eval(), so
 * each interface is checked by reflection before any code is generated.
 */
class InterceptorClassGenerator
{
    /**
     * Method names the interception trait and marker interface already define on the wrapper.
     */
    private const array RESERVED_METHOD_NAMES = [
        'initInterception',
        'getPluginTarget',
        'interceptCall',
        'interceptParentCall',
        'runBeforePlugins',
        'runAfterPlugins',
    ];

    /**
     * @var array<string, string> Cache of target class => generated class name
     */
    private array $cache = [];

    /**
     * Generate and eval an interface wrapper class, returning the generated class name.
     *
     * @param class-string $interfaceName
     * @param class-string|null $concreteClass The class behind the interface; its other interfaces
     *     are kept where the wrapper can reproduce them
     * @throws PluginException|ReflectionException
     */
    public function generateInterfaceWrapper(
        string $interfaceName,
        PluginRegistry $pluginRegistry,
        ?string $concreteClass = null,
    ): string {
        $cacheKey = 'wrapper:' . $interfaceName . ($concreteClass === null ? '' : ':' . $concreteClass);

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $code = $this->generateInterfaceWrapperCode($interfaceName, $pluginRegistry, $concreteClass);
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
     * @param class-string|null $concreteClass The class behind the interface; its other interfaces
     *     are kept where the wrapper can reproduce them
     * @throws PluginException|ReflectionException
     */
    public function generateInterfaceWrapperCode(
        string $interfaceName,
        PluginRegistry $pluginRegistry,
        ?string $concreteClass = null,
    ): string {
        $reflection = new ReflectionClass($interfaceName);
        $otherInterfaces = $this->collectOtherInterfaces($reflection, $concreteClass, $pluginRegistry);
        $className = $this->buildClassName($interfaceName);

        if ($otherInterfaces !== []) {
            $className .= '_' . substr(md5(implode(',', $otherInterfaces)), 0, 8);
        }

        $implements = implode(', ', array_map(
            fn (string $name) => '\\' . $name,
            [$interfaceName, ...$otherInterfaces],
        ));

        $methods = [];

        foreach ($this->collectInterfaceMethods($reflection) as $method) {
            $methods[$method->getName()] = $this->generateWrapperMethod($method);
        }

        foreach ($otherInterfaces as $otherInterface) {
            foreach ($this->collectInterfaceMethods(new ReflectionClass($otherInterface)) as $method) {
                $methods[$method->getName()] ??= $this->generateDelegatedMethod($method);
            }
        }

        $methodCode = implode("\n\n", $methods);

        return <<<PHP
        class $className implements $implements, \\Marko\\Core\\Plugin\\PluginInterceptedInterface
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

        if ($reflection->isFinal()) {
            throw PluginException::cannotInterceptFinal($targetClass);
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
    private function loadCode(
        string $code,
    ): void {
        eval($code);
    }

    /**
     * Build a deterministic class name for the given target.
     */
    private function buildClassName(
        string $targetClass,
    ): string {
        $sanitized = str_replace('\\', '_', $targetClass);
        $hash = substr(md5($targetClass), 0, 8);

        return "Marko_Interceptor_{$sanitized}_$hash";
    }

    /**
     * Extract the class name from a generated code snippet.
     */
    private function extractClassName(
        string $code,
    ): string {
        preg_match('/class (Marko_Interceptor_\S+)\s/', $code, $matches);

        return $matches[1];
    }

    /**
     * Collect the other interfaces of the concrete class that the wrapper keeps, sorted by name.
     *
     * The plugged interface, its parents and the marker interface are already on the wrapper.
     * Each remaining interface is kept only if canKeepInterface() accepts it and it does not
     * clash with the plugged interface or one kept before it; an interface left out behaves as
     * it did before.
     *
     * @param ReflectionClass<object> $pluggedInterface
     * @param class-string|null $concreteClass
     *
     * @return array<class-string>
     * @throws PluginException|ReflectionException
     */
    private function collectOtherInterfaces(
        ReflectionClass $pluggedInterface,
        ?string $concreteClass,
        PluginRegistry $pluginRegistry,
    ): array {
        if ($concreteClass === null) {
            return [];
        }

        $excluded = [
            ...$pluggedInterface->getInterfaceNames(),
            $pluggedInterface->getName(),
            PluginInterceptedInterface::class,
        ];
        $others = array_values(array_filter(
            array_diff(class_implements($concreteClass), $excluded),
            fn (string $interface) => $this->canKeepInterface($interface, $excluded, $pluginRegistry),
        ));
        sort($others);

        return $this->dropClashingInterfaces($pluggedInterface, $others);
    }

    /**
     * Drop each interface that declares a method already emitted with a different signature,
     * or a constant already declared by a different emitted interface.
     *
     * @param ReflectionClass<object> $pluggedInterface
     * @param array<class-string> $candidates Sorted by name
     *
     * @return array<class-string>
     * @throws PluginException|ReflectionException
     */
    private function dropClashingInterfaces(
        ReflectionClass $pluggedInterface,
        array $candidates,
    ): array {
        $signatures = $this->collectSignatures($pluggedInterface);
        $constantOwners = $this->collectConstantOwners($pluggedInterface);
        $kept = [];

        foreach ($candidates as $candidate) {
            $candidateReflection = new ReflectionClass($candidate);
            $candidateSignatures = $this->collectSignatures($candidateReflection);
            $candidateOwners = $this->collectConstantOwners($candidateReflection);

            if (
                array_any(
                    $candidateSignatures,
                    fn (string $signature, string $name) => isset($signatures[$name])
                        && $signatures[$name] !== $signature,
                )
                || array_any(
                    $candidateOwners,
                    fn (string $owner, string $name) => isset($constantOwners[$name])
                        && $constantOwners[$name] !== $owner,
                )
            ) {
                continue;
            }

            $signatures += $candidateSignatures;
            $constantOwners += $candidateOwners;
            $kept[] = $candidate;
        }

        return $kept;
    }

    /**
     * Map each constant name of an interface to the interface that declares it.
     *
     * @param ReflectionClass<object> $interface
     *
     * @return array<string, string>
     */
    private function collectConstantOwners(
        ReflectionClass $interface,
    ): array {
        $owners = [];

        foreach ($interface->getReflectionConstants() as $constant) {
            $owners[$constant->getName()] = $constant->getDeclaringClass()->getName();
        }

        return $owners;
    }

    /**
     * Map each method name of an interface to its delegated signature.
     *
     * @param ReflectionClass<object> $interface
     *
     * @return array<string, string>
     * @throws PluginException|ReflectionException
     */
    private function collectSignatures(
        ReflectionClass $interface,
    ): array {
        $signatures = [];

        foreach ($this->collectInterfaceMethods($interface) as $method) {
            $signatures[$method->getName()] = $this->renderParameters($method, delegated: true)
                . $this->renderReturnType($method);
        }

        return $signatures;
    }

    /**
     * Decide whether the wrapper can safely implement another interface of the concrete class.
     *
     * Refused when the interface or any parent is internal to PHP, when it or an uncovered parent
     * has plugins of its own (delegating would skip them), when it declares a property (own or
     * inherited; the wrapper reproduces methods only), or when any of its methods is unreproducible. Deciding here, by reflection, avoids a fatal error in eval().
     *
     * @param class-string $interface
     * @param array<class-string> $coveredInterfaces The plugged interface, its parents and the marker interface
     * @throws ReflectionException
     */
    private function canKeepInterface(
        string $interface,
        array $coveredInterfaces,
        PluginRegistry $pluginRegistry,
    ): bool {
        $hierarchy = [$interface, ...array_values(class_implements($interface))];

        if (array_any($hierarchy, fn (string $name) => new ReflectionClass($name)->isInternal())) {
            return false;
        }

        if (array_any(
            array_diff($hierarchy, $coveredInterfaces),
            fn (string $name) => $pluginRegistry->hasPluginsFor($name),
        )) {
            return false;
        }

        $reflection = new ReflectionClass($interface);

        if ($reflection->getProperties() !== []) {
            return false;
        }

        return !array_any(
            $this->collectInterfaceMethods($reflection),
            fn (ReflectionMethod $method) => $this->isUnreproducible($method),
        );
    }

    /**
     * Check if a delegated method has a shape the generated wrapper cannot declare exactly.
     *
     * Static methods have no target instance to call, reserved names collide with the
     * interception trait, a static return would get the target instead of the wrapper, and
     * object defaults cannot be rendered as a constant expression.
     *
     * @throws ReflectionException
     */
    private function isUnreproducible(
        ReflectionMethod $method,
    ): bool {
        return $method->isStatic()
            || $method->returnsReference()
            || in_array($method->getName(), self::RESERVED_METHOD_NAMES, true)
            || $this->containsStaticType($method->getReturnType())
            || array_any($method->getParameters(), fn (ReflectionParameter $p) => $this->hasObjectDefault($p));
    }

    /**
     * Check if a type is or contains `static`.
     */
    private function containsStaticType(
        ?ReflectionType $type,
    ): bool {
        if ($type instanceof ReflectionNamedType) {
            return $type->getName() === 'static';
        }

        if (
            $type instanceof ReflectionUnionType
            || $type instanceof ReflectionIntersectionType
        ) {
            return array_any($type->getTypes(), fn (ReflectionType $t) => $this->containsStaticType($t));
        }

        return false;
    }

    /**
     * Check if a parameter default is, or holds, an object that renderValue() would refuse.
     *
     * @throws ReflectionException
     */
    private function hasObjectDefault(
        ReflectionParameter $param,
    ): bool {
        return !$param->isVariadic()
            && $param->isOptional()
            && $param->isDefaultValueAvailable()
            && $this->containsObject($param->getDefaultValue());
    }

    private function containsObject(
        mixed $value,
    ): bool {
        if (is_array($value)) {
            return array_any($value, fn (mixed $item) => $this->containsObject($item));
        }

        return is_object($value)
            && !($value instanceof UnitEnum);
    }

    /**
     * Collect all methods from a reflected interface, including inherited ones.
     *
     * @param ReflectionClass<object> $reflection
     *
     * @return array<ReflectionMethod>
     */
    private function collectInterfaceMethods(
        ReflectionClass $reflection,
    ): array {
        return array_filter(
            $reflection->getMethods(),
            fn (ReflectionMethod $m) => !$m->isConstructor(),
        );
    }

    /**
     * Generate a single method stub for the interface wrapper strategy.
     */
    private function generateWrapperMethod(
        ReflectionMethod $method,
    ): string {
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
     * Generate a method that calls the target directly, bypassing the plugin chain.
     *
     * @throws PluginException|ReflectionException
     */
    private function generateDelegatedMethod(
        ReflectionMethod $method,
    ): string {
        $name = $method->getName();
        $params = $this->renderParameters($method, delegated: true);
        $returnType = $this->renderReturnType($method);
        $args = implode(', ', array_map(
            fn (ReflectionParameter $p) => ($p->isVariadic() ? '...' : '') . '$' . $p->getName(),
            $method->getParameters(),
        ));
        $call = "\$this->pluginTarget->$name($args);";
        $body = $this->returnsNothing($method)
            ? $call
            : "return $call";

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
    private function generateSubclassMethod(
        ReflectionMethod $method,
    ): string {
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
     *
     * @throws PluginException|ReflectionException
     */
    private function renderParameters(
        ReflectionMethod $method,
        bool $delegated = false,
    ): string {
        return implode(', ', array_map(
            fn (ReflectionParameter $p) => $this->renderParameter($p, $delegated),
            $method->getParameters(),
        ));
    }

    /**
     * Render a single parameter declaration.
     *
     * Only a delegated method keeps by-reference markers, since it passes its arguments straight
     * to the target; the intercepted wrapper's output is unchanged.
     *
     * @throws PluginException|ReflectionException
     */
    private function renderParameter(
        ReflectionParameter $param,
        bool $delegated = false,
    ): string {
        $parts = [];

        if ($param->hasType()) {
            $parts[] = $this->renderType($param->getType());
        }

        $referencePrefix = ($delegated && $param->isPassedByReference())
            ? '&'
            : '';
        $variadicPrefix = $param->isVariadic() ? '...' : '';
        $parts[] = $referencePrefix . $variadicPrefix . '$' . $param->getName();

        if (
            !$param->isVariadic()
            && $param->isOptional()
            && $param->isDefaultValueAvailable()
        ) {
            $parts[] = '= ' . $this->renderDefaultValue($param);
        }

        return implode(' ', $parts);
    }

    /**
     * Render the return type annotation string (e.g. ": string").
     */
    private function renderReturnType(
        ReflectionMethod $method,
    ): string {
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

            if (
                !$type->isBuiltin()
                && $name !== 'self'
                && $name !== 'static'
                && $name !== 'parent'
            ) {
                $name = '\\' . $name;
            }

            return ($type->allowsNull() && $name !== 'null' && $name !== 'mixed')
                ? '?' . $name
                : $name;
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
     * Render the default value of a parameter as a PHP constant expression.
     *
     * The evaluated value is rendered rather than the original source expression: constant
     * references such as self::X, parent::X, private constants or unqualified global constants
     * do not resolve correctly from the generated class, but their (immutable) values do.
     *
     * @throws PluginException|ReflectionException
     */
    private function renderDefaultValue(
        ReflectionParameter $param,
    ): string {
        return $this->renderValue($param->getDefaultValue(), $param);
    }

    /**
     * Render a default value (recursively for arrays) as a PHP constant expression.
     *
     * @throws PluginException
     */
    private function renderValue(
        mixed $value,
        ReflectionParameter $param,
    ): string {
        if (is_array($value)) {
            $items = [];

            foreach ($value as $key => $item) {
                $items[] = var_export($key, true) . ' => ' . $this->renderValue($item, $param);
            }

            return '[' . implode(', ', $items) . ']';
        }

        if ($value instanceof UnitEnum) {
            return '\\' . $value::class . '::' . $value->name;
        }

        if (is_object($value)) {
            $method = $param->getDeclaringFunction();
            $class = $param->getDeclaringClass()?->getName() ?? '';

            throw PluginException::unsupportedDefaultValue(
                $class . '::' . $method->getName(),
                $param->getName(),
                $value::class,
            );
        }

        // null, bool, int, float and string: var_export produces an exact, eval-safe literal
        // (floats round-trip, strings are escaped for single quotes, backslashes and NUL bytes)
        return var_export($value, true);
    }

    /**
     * Render the arguments array literal passed to interceptCall / interceptParentCall.
     */
    private function renderArgumentsArray(
        ReflectionMethod $method,
    ): string {
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
     * Check if a method returns nothing to its caller: a void or never return type.
     */
    private function returnsNothing(
        ReflectionMethod $method,
    ): bool {
        $type = $method->getReturnType();

        return $type instanceof ReflectionNamedType
            && in_array($type->getName(), ['void', 'never'], true);
    }

    /**
     * Check if a method has a void return type.
     */
    private function isVoidReturn(
        ReflectionMethod $method,
    ): bool {
        if (!$method->hasReturnType()) {
            return false;
        }

        $type = $method->getReturnType();

        return $type instanceof ReflectionNamedType
            && $type->getName() === 'void';
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
