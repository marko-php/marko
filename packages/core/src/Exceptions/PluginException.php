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
}
