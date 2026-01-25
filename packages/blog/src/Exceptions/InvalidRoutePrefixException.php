<?php

declare(strict_types=1);

namespace Marko\Blog\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class InvalidRoutePrefixException extends MarkoException
{
    public static function mustStartWithSlash(
        string $prefix,
    ): self {
        return new self(
            message: "Route prefix '$prefix' must start with a forward slash",
            context: "Configured value: $prefix",
            suggestion: "Change the route_prefix to start with '/' (e.g., '/blog' instead of 'blog')",
        );
    }

    public static function mustNotEndWithSlash(
        string $prefix,
    ): self {
        return new self(
            message: "Route prefix '$prefix' must not end with a forward slash",
            context: "Configured value: $prefix",
            suggestion: "Remove the trailing slash from route_prefix (e.g., '/blog' instead of '/blog/')",
        );
    }
}
