<?php

declare(strict_types=1);

namespace Marko\Config\Discovery;

use Marko\Config\Env;
use Marko\Config\Exceptions\ConfigCacheException;
use Marko\Config\Exceptions\ConfigException;
use Marko\Core\Exceptions\DiscoveryCacheException;

/**
 * The shape of the config section of the discovery cache, shared by
 * ConfigCacheContributor (which builds it) and ConfigServiceProvider (which
 * reads it back on a cached boot).
 *
 * The section holds the merged config values and, for every environment
 * variable the config files read through Env, a hash of its value at build
 * time. Reading the section re-hashes just those variables, so a changed
 * environment fails loudly instead of booting with stale config.
 */
readonly class ConfigCacheSection
{
    /**
     * @param array<mixed> $values Merged config, keyed by config file name
     * @param list<string> $envNames Variables read through Env while the config files ran
     * @return array{values: array<mixed>, env: array<string, string>}
     *
     * @throws ConfigException
     */
    public function build(
        array $values,
        array $envNames,
    ): array {
        return [
            'values' => $values,
            'env' => Env::valueHashes($envNames),
        ];
    }

    /**
     * The cached config values, after checking the environment still matches.
     *
     * @param array<mixed> $section
     * @return array<mixed>
     *
     * @throws DiscoveryCacheException|ConfigCacheException|ConfigException
     */
    public function values(
        array $section,
    ): array {
        if (!isset($section['values']) || !is_array($section['values'])) {
            throw DiscoveryCacheException::malformedSection(ConfigCacheContributor::KEY, "'values' must be an array");
        }

        $hashes = $section['env'] ?? null;

        if (
            !is_array($hashes)
            || !array_all($hashes, fn (mixed $hash, int|string $name): bool => is_string($name) && is_string($hash))
        ) {
            throw DiscoveryCacheException::malformedSection(
                ConfigCacheContributor::KEY,
                "'env' must map variable names to hashes",
            );
        }

        /** @var array<string, string> $hashes */
        $current = Env::valueHashes(array_keys($hashes));
        $changed = array_keys(array_diff_assoc($hashes, $current));

        if ($changed !== []) {
            throw ConfigCacheException::environmentChanged($changed);
        }

        return $section['values'];
    }
}
