<?php

declare(strict_types=1);

namespace Marko\Core\Discovery;

use Marko\Core\Environment\AppEnvironment;
use Marko\Core\Environment\EnvironmentVariables;
use Marko\Core\Exceptions\DiscoveryCacheException;

/**
 * Boot-time reader for the discovery cache settings.
 *
 * Reads through EnvironmentVariables ($_ENV with a getenv() fallback), so it
 * works whether or not marko/env is installed and regardless of PHP's
 * variables_order setting, and a recording of environment reads (the config
 * section of the discovery cache) sees every variable it read.
 */
class DiscoveryEnvironment
{
    private const array TRUE_VALUES = ['1', 'true', 'yes', 'on'];

    /** An empty value disables the cache, as documented for DISCOVERY_CACHE_ENABLED. */
    private const array FALSE_VALUES = ['0', 'false', 'no', 'off', ''];

    public function __construct(
        private readonly AppEnvironment $appEnvironment = new AppEnvironment(),
    ) {}

    /**
     * @throws DiscoveryCacheException
     */
    public function enabled(): bool
    {
        $value = EnvironmentVariables::read('DISCOVERY_CACHE_ENABLED');

        if ($value === null) {
            return true;
        }

        $token = strtolower(trim($value));

        if (in_array($token, self::TRUE_VALUES, strict: true)) {
            return true;
        }

        if (in_array($token, self::FALSE_VALUES, strict: true)) {
            return false;
        }

        throw DiscoveryCacheException::invalidEnabledValue($value);
    }

    public function environment(): string
    {
        return $this->appEnvironment->name();
    }

    /**
     * The cache file is included as PHP, so it must live in a directory only
     * the application user can write to; DiscoveryCache refuses to load it
     * from a world-writable or foreign-owned location.
     */
    public function cachePath(): string
    {
        return EnvironmentVariables::read('DISCOVERY_CACHE_PATH') ?? 'storage/cache/discovery.php';
    }
}
