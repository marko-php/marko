<?php

declare(strict_types=1);

namespace Marko\Core\Discovery;

class DiscoveryEnvironment
{
    private const array FALSE_VALUES = ['0', 'false', 'no', 'off', ''];

    public function enabled(): bool
    {
        if (!array_key_exists('DISCOVERY_CACHE_ENABLED', $_ENV)) {
            return true;
        }

        return !in_array(strtolower((string) $_ENV['DISCOVERY_CACHE_ENABLED']), self::FALSE_VALUES, strict: true);
    }

    public function environment(): string
    {
        return $_ENV['APP_ENV'] ?? 'production';
    }

    public function cachePath(): string
    {
        return $_ENV['DISCOVERY_CACHE_PATH'] ?? 'storage/cache/discovery.php';
    }
}
