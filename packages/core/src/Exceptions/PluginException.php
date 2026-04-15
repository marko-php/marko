<?php

declare(strict_types=1);

namespace Marko\Core\Exceptions;

class PluginException extends MarkoException
{
    public static function invalidConfiguration(
        string $pluginClass,
        string $reason,
    ): self {
        return new self(
            message: "Invalid plugin configuration for '$pluginClass': $reason",
            context: "While registering plugin '$pluginClass'",
            suggestion: 'Ensure your plugin class implements the correct interface and has valid #[Before] or #[After] attributes',
        );
    }

    public static function missingPluginAttribute(
        string $className,
    ): self {
        return new self(
            message: "Class '$className' is not a valid plugin: missing #[Plugin] attribute",
            context: "While parsing plugin class '$className'",
            suggestion: 'Add the #[Plugin(target: TargetClass::class)] attribute to declare the class as a plugin',
        );
    }

    /**
     * @param array<string> $methodNames
     */
    public static function orphanedPluginMethods(
        string $className,
        array $methodNames,
    ): self {
        $methods = implode(', ', $methodNames);

        return new self(
            message: "Class '$className' has #[Before] or #[After] methods but is not a plugin: $methods",
            context: "While validating plugin methods in '$className'",
            suggestion: 'Add the #[Plugin(target: TargetClass::class)] attribute to the class, or remove the #[Before]/#[After] attributes from the methods',
        );
    }

    /**
     * @param array<string> $methodNames
     */
    public static function duplicatePluginHook(
        string $pluginClass,
        string $timing,
        string $targetMethod,
        array $methodNames,
    ): self {
        $methods = implode(', ', $methodNames);

        return new self(
            message: "Plugin class '$pluginClass' has multiple $timing hooks targeting '$targetMethod': $methods",
            context: "While parsing plugin class '$pluginClass'",
            suggestion: "Each target method can only have one $timing hook per plugin class. Use separate plugin classes for additional hooks.",
        );
    }

    public static function cannotTargetPlugin(
        string $pluginClass,
        string $targetClass,
    ): self {
        return new self(
            message: "Plugin '$pluginClass' cannot target '$targetClass' because '$targetClass' is itself a plugin class",
            context: "While registering plugin '$pluginClass' targeting '$targetClass'",
            suggestion: "Plugins cannot modify other plugins. Use a Preference to replace '$targetClass' entirely if you need to change its behavior.",
        );
    }

    /**
     * @param array<string> $interfaces
     */
    public static function ambiguousInterfacePlugins(
        string $concreteClass,
        array $interfaces,
    ): self {
        $interfaceList = implode(', ', $interfaces);

        return new self(
            message: "Cannot determine which interface to intercept for '$concreteClass': multiple interfaces have plugins registered ($interfaceList)",
            context: "While resolving plugin interceptor for '$concreteClass'",
            suggestion: "Target the concrete class directly with #[Plugin(target: $concreteClass::class)] instead of targeting multiple interfaces",
        );
    }

    public static function cannotInterceptReadonly(
        string $targetClass,
    ): self {
        return new self(
            message: "Cannot create interceptor for '$targetClass': readonly classes cannot be extended",
            context: "While creating plugin interceptor for '$targetClass'",
            suggestion: "Target the interface instead of the readonly class. Use #[Plugin(target: InterfaceName::class)] where InterfaceName is an interface that '$targetClass' implements",
        );
    }

    public static function conflictingSortOrder(
        string $targetClass,
        string $targetMethod,
        string $timing,
        string $pluginClass1,
        string $pluginClass2,
        int $sortOrder,
    ): self {
        return new self(
            message: "Two plugins have conflicting sort order ($sortOrder) for $timing hook on '$targetClass::$targetMethod()': $pluginClass1 and $pluginClass2",
            context: "While registering plugin '$pluginClass2' for '$targetClass'",
            suggestion: 'Change the sortOrder on one of the plugins to make execution order deterministic.',
        );
    }
}
