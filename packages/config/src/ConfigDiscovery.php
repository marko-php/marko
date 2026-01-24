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
     * @throws ConfigLoadException
     */
    public function discover(
        array $modulePaths,
        string $rootConfigPath,
    ): array {
        $result = [];

        foreach ($modulePaths as $modulePath) {
            $result = $this->mergeConfigFromDirectory(
                result: $result,
                directory: $modulePath . '/config',
            );
        }

        // Merge root config (highest priority)
        return $this->mergeConfigFromDirectory(
            result: $result,
            directory: $rootConfigPath,
        );
    }

    /**
     * @throws ConfigLoadException
     */
    private function mergeConfigFromDirectory(
        array $result,
        string $directory,
    ): array {
        if (!is_dir($directory)) {
            return $result;
        }

        $files = glob($directory . '/*.php');

        foreach ($files as $file) {
            $key = pathinfo($file, PATHINFO_FILENAME);
            $config = $this->loader->load($file);
            $result[$key] = isset($result[$key])
                ? $this->merger->merge($result[$key], $config)
                : $config;
        }

        return $result;
    }
}
