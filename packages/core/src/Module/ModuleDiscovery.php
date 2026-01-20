<?php

declare(strict_types=1);

namespace Marko\Core\Module;

use Marko\Core\Exceptions\ModuleException;

/**
 * Discovers Marko modules in configured directories.
 *
 * A directory is a Marko module if it contains:
 * - composer.json (required - provides name, version, dependencies)
 * - module.php (optional - provides Marko-specific config like bindings)
 *
 * Discovery depths vary by source:
 * - vendor: Two levels deep (vendor/vendor-name/package-name/)
 * - modules: Recursive at any depth
 * - app: One level deep (app/module-name/)
 */
readonly class ModuleDiscovery
{
    public function __construct(
        private ManifestParser $parser,
    ) {}

    /**
     * Discover modules in vendor directory (two levels deep: vendor/vendor-name/package-name/)
     *
     * @return array<ModuleManifest>
     * @throws ModuleException
     */
    public function discoverInVendor(
        string $vendorDir,
    ): array {
        $modules = [];

        if (!is_dir($vendorDir)) {
            return $modules;
        }

        // Scan vendor/*/
        $vendorNames = $this->scanDirectory($vendorDir);

        foreach ($vendorNames as $vendorName) {
            $vendorPath = $vendorDir . '/' . $vendorName;

            if (!is_dir($vendorPath)) {
                continue;
            }

            // Scan vendor/vendor-name/*/
            $packageNames = $this->scanDirectory($vendorPath);

            foreach ($packageNames as $packageName) {
                $packagePath = $vendorPath . '/' . $packageName;

                if ($this->isMarkoModule($packagePath)) {
                    $manifest = $this->parser->parse($packagePath);
                    $modules[] = $this->withPathAndSource($manifest, $packagePath, 'vendor');
                }
            }
        }

        return $modules;
    }

    /**
     * Discover modules in modules directory (recursive, any depth)
     *
     * @return array<ModuleManifest>
     * @throws ModuleException
     */
    public function discoverInModules(
        string $modulesDir,
    ): array {
        $modules = [];

        if (!is_dir($modulesDir)) {
            return $modules;
        }

        $this->discoverRecursively($modulesDir, 'modules', $modules);

        return $modules;
    }

    /**
     * Discover modules in app directory (one level deep: app/module-name/)
     *
     * @return array<ModuleManifest>
     * @throws ModuleException
     */
    public function discoverInApp(
        string $appDir,
    ): array {
        $modules = [];

        if (!is_dir($appDir)) {
            return $modules;
        }

        // Scan app/*/
        $moduleNames = $this->scanDirectory($appDir);

        foreach ($moduleNames as $moduleName) {
            $modulePath = $appDir . '/' . $moduleName;

            if (is_dir($modulePath) && $this->isMarkoModule($modulePath)) {
                $manifest = $this->parser->parse($modulePath);
                $modules[] = $this->withPathAndSource($manifest, $modulePath, 'app');
            }
        }

        return $modules;
    }

    /**
     * Check if a directory is a Marko module.
     *
     * A Marko module must have composer.json (required for all modules).
     * module.php is optional but commonly present for Marko-specific config.
     */
    private function isMarkoModule(
        string $path,
    ): bool {
        // composer.json is required for all modules
        return is_file($path . '/composer.json');
    }

    /**
     * Create a new manifest with path and source set.
     */
    private function withPathAndSource(
        ModuleManifest $manifest,
        string $path,
        string $source,
    ): ModuleManifest {
        return new ModuleManifest(
            name: $manifest->name,
            version: $manifest->version,
            enabled: $manifest->enabled,
            require: $manifest->require,
            after: $manifest->after,
            before: $manifest->before,
            bindings: $manifest->bindings,
            path: $path,
            source: $source,
            autoload: $manifest->autoload,
        );
    }

    /**
     * @param array<ModuleManifest> $modules
     * @throws ModuleException
     */
    private function discoverRecursively(
        string $dir,
        string $source,
        array &$modules,
    ): void {
        if ($this->isMarkoModule($dir)) {
            $manifest = $this->parser->parse($dir);
            $modules[] = $this->withPathAndSource($manifest, $dir, $source);

            return; // Don't recurse into module directories
        }

        // No module found, recurse into subdirectories
        $items = $this->scanDirectory($dir);

        foreach ($items as $item) {
            $itemPath = $dir . '/' . $item;

            if (is_dir($itemPath)) {
                $this->discoverRecursively($itemPath, $source, $modules);
            }
        }
    }

    /**
     * @return array<string>
     */
    private function scanDirectory(
        string $dir,
    ): array {
        $items = scandir($dir);

        if ($items === false) {
            return [];
        }

        return array_filter($items, fn (string $item) => $item !== '.' && $item !== '..');
    }
}
