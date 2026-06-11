<?php

declare(strict_types=1);

namespace Marko\Translation\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class TranslationException extends MarkoException
{
    public static function invalidPathSegment(
        string $segment,
        string $value,
    ): self {
        return new self(
            message: "Invalid translation path segment '$value' for $segment",
            context: "The $segment '$value' contains characters that are not allowed in file path segments",
            suggestion: "Use only letters, digits, underscores, and hyphens (A-Za-z0-9_-) in $segment names",
        );
    }

    public static function namespaceNotRegistered(
        string $namespace,
        string $locale,
        string $group,
    ): self {
        return new self(
            message: "Translation namespace '$namespace' is not registered",
            context: "Namespace: $namespace, Locale: $locale, Group: $group",
            suggestion: "Register the namespace with \$loader->addNamespace('$namespace', '/path/to/$namespace/lang')",
        );
    }
}
