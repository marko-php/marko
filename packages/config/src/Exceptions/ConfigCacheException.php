<?php

declare(strict_types=1);

namespace Marko\Config\Exceptions;

use Marko\Core\Exceptions\DiscoveryCacheException;

/**
 * The config section of the discovery cache cannot be built, or no longer
 * matches the environment it was built in.
 */
class ConfigCacheException extends DiscoveryCacheException
{
    public static function unexportableValue(
        string $key,
        string $file,
        string $type,
    ): self {
        return new self(
            message: "Config value '$key' in $file is a $type and cannot be stored in the discovery cache",
            context: 'While compiling the config section of the discovery cache (marko discovery:cache)',
            suggestion: 'The discovery cache stores config as plain data, so config values may only be strings, numbers, booleans, null and arrays. Store a class name (string) and resolve it through the container instead of putting a closure or object in config.',
        );
    }

    /**
     * @param list<string> $names
     */
    public static function environmentChanged(
        array $names,
    ): self {
        $list = implode(', ', $names);

        return new self(
            message: "Environment variables changed since the discovery cache was built: $list",
            context: 'While loading config from the discovery cache. Config values are fixed when `marko discovery:cache` runs, so the cached config no longer matches the environment',
            suggestion: 'Run `marko discovery:cache` again after changing environment variables (it is a required step on every deploy), or `marko discovery:clear` to load config live',
        );
    }
}
