<?php

declare(strict_types=1);

namespace Marko\Core\Discovery;

use Marko\Core\Path\ProjectPaths;

/**
 * Cheap fingerprint of what module discovery would find, so a stale
 * discovery cache is detected at boot without re-running discovery.
 *
 * Covers the contents of vendor/composer/installed.json (any Composer
 * install, update or removal) and each directory holding a composer.json
 * under modules/ (recursive, stopping at a module) and app/ (one level),
 * together with a hash of that composer.json, plus every root config/*.php
 * file (the cache holds the merged config). Files are hashed, never decoded
 * or included, and vendor/ is never scanned.
 */
class DiscoveryFingerprint
{
    public function compute(
        ProjectPaths $projectPaths,
    ): string {
        $installed = $projectPaths->vendor . '/composer/installed.json';
        $parts = [is_file($installed) ? (string) hash_file('xxh128', $installed) : 'no-installed-json'];

        $directories = [
            ...$this->modulesDirectories($projectPaths->modules),
            ...$this->appDirectories($projectPaths->app),
        ];
        sort($directories);

        $prefix = $projectPaths->base . '/';

        foreach ($directories as $directory) {
            $relative = str_starts_with($directory, $prefix) ? substr($directory, strlen($prefix)) : $directory;
            $parts[] = $relative . ':' . hash_file('xxh128', $directory . '/composer.json');
        }

        foreach ($this->rootConfigFiles($projectPaths->config) as $file) {
            $parts[] = 'config/' . basename($file) . ':' . hash_file('xxh128', $file);
        }

        return hash('xxh128', implode("\n", $parts));
    }

    /**
     * @return array<int, string>
     */
    private function modulesDirectories(
        string $directory,
    ): array {
        if (!is_dir($directory)) {
            return [];
        }

        if (is_file($directory . '/composer.json')) {
            return [$directory];
        }

        $found = [];

        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..' || !is_dir($directory . '/' . $item)) {
                continue;
            }

            array_push($found, ...$this->modulesDirectories($directory . '/' . $item));
        }

        return $found;
    }

    /**
     * @return array<int, string>
     */
    private function rootConfigFiles(
        string $directory,
    ): array {
        if (!is_dir($directory)) {
            return [];
        }

        $files = glob($directory . '/*.php') ?: [];
        sort($files);

        return $files;
    }

    /**
     * @return array<int, string>
     */
    private function appDirectories(
        string $directory,
    ): array {
        if (!is_dir($directory)) {
            return [];
        }

        return array_map(dirname(...), glob($directory . '/*/composer.json') ?: []);
    }
}
