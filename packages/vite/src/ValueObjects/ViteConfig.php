<?php

declare(strict_types=1);

namespace Marko\Vite\ValueObjects;

readonly class ViteConfig
{
    /**
     * @param array<string> $defaultEntrypoints
     */
    public function __construct(
        public string $devServerUrl,
        public string $devProcessFilePath,
        public string $hotFilePath,
        public string $manifestPath,
        public string $buildDirectory,
        public string $assetsBaseUrl,
        public array $defaultEntrypoints,
        public string $rootEntrypointPath,
        public string $rootViteConfigPath,
    ) {}

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(
        array $config,
        ?string $basePath = null,
    ): self
    {
        return new self(
            devServerUrl: (string) ($config['dev_server_url'] ?? 'http://localhost:5173'),
            devProcessFilePath: self::resolvePath(
                (string) ($config['dev_process_file_path'] ?? '.marko/dev.json'),
                $basePath
            ),
            hotFilePath: self::resolvePath((string) ($config['hot_file_path'] ?? 'public/hot'), $basePath),
            manifestPath: self::resolvePath(
                (string) ($config['manifest_path'] ?? 'public/build/manifest.json'),
                $basePath
            ),
            buildDirectory: (string) ($config['build_directory'] ?? '/build'),
            assetsBaseUrl: (string) ($config['assets_base_url'] ?? ''),
            defaultEntrypoints: array_values(array_map('strval', $config['default_entrypoints'] ?? [])),
            rootEntrypointPath: (string) ($config['root_entrypoint_path'] ?? 'resources/js/app.ts'),
            rootViteConfigPath: (string) ($config['root_vite_config_path'] ?? 'vite.config.ts'),
        );
    }

    private static function resolvePath(
        string $path,
        ?string $basePath,
    ): string
    {
        if ($basePath === null || $path === '') {
            return $path;
        }

        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
            return $path;
        }

        return rtrim($basePath, '/\\') . '/' . ltrim($path, '/\\');
    }
}
