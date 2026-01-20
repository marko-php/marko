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

    public static function missingPluginAttribute(string $className): self
    {
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
}
