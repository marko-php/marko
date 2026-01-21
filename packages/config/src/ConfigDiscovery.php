<?php

declare(strict_types=1);

namespace Marko\Config;

readonly class ConfigDiscovery
{
    public function __construct(
        private ConfigLoader $loader,
        private ConfigMerger $merger,
    ) {}

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
        $result = $this->mergeConfigFromDirectory(
            result: $result,
            directory: $rootConfigPath,
        );

        return $result;
    }

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
