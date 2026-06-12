<?php

declare(strict_types=1);

namespace Marko\Core\Exceptions;

class DiscoveryCacheException extends MarkoException
{
    public static function unreadable(string $path): self
    {
        return new self(
            message: "Discovery cache file '$path' could not be read",
            context: "While loading the discovery cache from '$path'",
            suggestion: "Delete '$path' to force re-discovery, or run vendor/bin/marko discovery:clear",
        );
    }

    public static function malformed(
        string $path,
        string $reason,
    ): self {
        return new self(
            message: "Discovery cache file '$path' is malformed: $reason",
            context: "While loading the discovery cache from '$path'",
            suggestion: "Delete '$path' to force re-discovery, or run vendor/bin/marko discovery:clear",
        );
    }

    public static function versionMismatch(
        string $path,
        int $found,
        int $expected,
    ): self {
        return new self(
            message: "Discovery cache version mismatch in '$path': found $found, expected $expected",
            context: "While loading the discovery cache from '$path'",
            suggestion: "Delete '$path' to force re-discovery, or run vendor/bin/marko discovery:clear",
        );
    }

    public static function notWritable(string $path): self
    {
        return new self(
            message: "Discovery cache file '$path' could not be written",
            context: "While writing the discovery cache to '$path'",
            suggestion: "Ensure the directory containing '$path' is writable by the web server or CLI user",
        );
    }
}
