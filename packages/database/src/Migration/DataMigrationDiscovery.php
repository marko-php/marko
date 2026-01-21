<?php

declare(strict_types=1);

namespace Marko\Database\Migration;

use Marko\Core\Path\ProjectPaths;

/**
 * Discovers data migrations across vendor, modules, and app directories.
 *
 * Data migrations are discovered in:
 * - vendor packages: Data directory
 * - third-party modules: Data directory
 * - application customizations: Data directory
 */
readonly class DataMigrationDiscovery
{
    private string $vendorPath;

    private string $modulesPath;

    private string $appPath;

    public function __construct(
        ProjectPaths $paths,
    ) {
        $this->vendorPath = $paths->vendor;
        $this->modulesPath = $paths->modules;
        $this->appPath = $paths->app;
    }

    /**
     * Discover all data migrations from all sources.
     *
     * @return array<array{name: string, path: string, source: string}>
     */
    public function discover(): array
    {
        $migrations = [];

        // Discover from vendor/*/*/Data/
        $migrations = array_merge($migrations, $this->discoverFromVendor());

        // Discover from modules/*/*/Data/
        $migrations = array_merge($migrations, $this->discoverFromModules());

        // Discover from app/*/Data/
        $migrations = array_merge($migrations, $this->discoverFromApp());

        // Sort by filename to ensure consistent order
        usort($migrations, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $migrations;
    }

    /**
     * Discover data migrations from vendor packages.
     *
     * @return array<array{name: string, path: string, source: string}>
     */
    private function discoverFromVendor(): array
    {
        if (!is_dir($this->vendorPath)) {
            return [];
        }

        $pattern = $this->vendorPath . '/*/*/Data/*.php';

        return $this->discoverFromPattern($pattern, 'vendor');
    }

    /**
     * Discover data migrations from modules.
     *
     * @return array<array{name: string, path: string, source: string}>
     */
    private function discoverFromModules(): array
    {
        if (!is_dir($this->modulesPath)) {
            return [];
        }

        $pattern = $this->modulesPath . '/*/*/Data/*.php';

        return $this->discoverFromPattern($pattern, 'modules');
    }

    /**
     * Discover data migrations from app.
     *
     * @return array<array{name: string, path: string, source: string}>
     */
    private function discoverFromApp(): array
    {
        if (!is_dir($this->appPath)) {
            return [];
        }

        $pattern = $this->appPath . '/*/Data/*.php';

        return $this->discoverFromPattern($pattern, 'app');
    }

    /**
     * Discover migrations from a glob pattern.
     *
     * @return array<array{name: string, path: string, source: string}>
     */
    private function discoverFromPattern(
        string $pattern,
        string $source,
    ): array {
        $files = glob($pattern);

        if ($files === false) {
            return [];
        }

        return array_map(fn (string $file): array => [
            'name' => pathinfo($file, PATHINFO_FILENAME),
            'path' => $file,
            'source' => $source,
        ], $files);
    }
}
