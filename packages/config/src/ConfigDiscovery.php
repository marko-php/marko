<?php

declare(strict_types=1);

namespace Marko\Config;

use Marko\Config\Exceptions\ConfigLoadException;

readonly class ConfigDiscovery
{
    public function __construct(
        private ConfigLoader $loader,
        private ConfigMerger $merger,
    ) {}

    /**
     * Load and merge every config file, in the order files() returns them.
     *
     * @param array<string> $modulePaths Paths to modules (containing config/ subdirectories)
     * @throws ConfigLoadException
     */
    public function discover(
        array $modulePaths,
        string $rootConfigPath,
    ): array {
        $result = [];

        foreach ($this->files($modulePaths, $rootConfigPath) as $file) {
            $key = pathinfo($file, PATHINFO_FILENAME);
            $config = $this->loader->load($file);
            // The first file of a name is merged into [] too, so RemoveKey
            // markers in it are dropped rather than stored as values.
            $result[$key] = $this->merger->merge($result[$key] ?? [], $config);
        }

        return $result;
    }

    /**
     * Every config file in merge order: each module's config/*.php in module
     * load order, then the root config directory (highest priority). A later
     * file overrides an earlier file with the same name.
     *
     * @param array<string> $modulePaths Paths to modules (containing config/ subdirectories)
     * @return list<string>
     */
    public function files(
        array $modulePaths,
        string $rootConfigPath,
    ): array {
        $directories = array_map(fn (string $path): string => $path . '/config', $modulePaths);
        $directories[] = $rootConfigPath;
        $files = [];

        foreach ($directories as $directory) {
            if (is_dir($directory)) {
                array_push($files, ...(glob($directory . '/*.php') ?: []));
            }
        }

        return $files;
    }
}
