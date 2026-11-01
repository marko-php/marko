<?php

declare(strict_types=1);

namespace Marko\Config\Discovery;

use Marko\Config\ConfigDiscovery;
use Marko\Config\ConfigLoader;
use Marko\Config\Env;
use Marko\Config\Exceptions\ConfigCacheException;
use Marko\Config\Exceptions\ConfigException;
use Marko\Config\Exceptions\ConfigLoadException;
use Marko\Core\Discovery\DiscoveryCacheContributorInterface;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Path\ProjectPaths;

/**
 * Stores the merged config in the discovery cache, so a cached boot builds
 * the ConfigRepository without finding, requiring or merging config files.
 *
 * compile() runs the same ConfigDiscovery as a live boot, over the enabled
 * modules and the root config directory, while Env records every variable
 * the config files read. ConfigCacheSection stores those names with a hash
 * of their values, and a cached boot fails loudly when one has changed.
 */
readonly class ConfigCacheContributor implements DiscoveryCacheContributorInterface
{
    public const string KEY = 'config';

    public function __construct(
        private ConfigDiscovery $discovery,
        private ConfigLoader $loader,
        private ProjectPaths $projectPaths,
        private ConfigCacheSection $section = new ConfigCacheSection(),
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    /**
     * @param array<ModuleManifest> $modules
     * @return array{values: array<mixed>, env: array<string, string>}
     *
     * @throws ConfigCacheException|ConfigException|ConfigLoadException
     */
    public function compile(
        array $modules,
    ): array {
        $modulePaths = array_map(fn (ModuleManifest $module): string => $module->path, array_values($modules));

        Env::startRecording();

        try {
            $values = $this->discovery->discover($modulePaths, $this->projectPaths->config);
        } finally {
            $envNames = Env::stopRecording();
        }

        $this->assertExportable($values, $modulePaths);

        return $this->section->build($values, $envNames);
    }

    /**
     * Only scalars, null and arrays survive var_export() as plain data.
     *
     * @param array<mixed> $values
     * @param array<string> $modulePaths
     *
     * @throws ConfigCacheException|ConfigLoadException
     */
    private function assertExportable(
        array $values,
        array $modulePaths,
    ): void {
        $violation = $this->findUnexportable($values, []);

        if ($violation === null) {
            return;
        }

        [$path, $type] = $violation;

        throw ConfigCacheException::unexportableValue(
            implode('.', $path),
            $this->sourceFile($path, $modulePaths),
            $type,
        );
    }

    /**
     * The key path and type of the first value that is not a scalar, null or array.
     *
     * @param array<int|string> $path
     * @return array{array<int|string>, string}|null
     */
    private function findUnexportable(
        mixed $value,
        array $path,
    ): ?array {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $violation = $this->findUnexportable($item, [...$path, $key]);

                if ($violation !== null) {
                    return $violation;
                }
            }

            return null;
        }

        return $value === null || is_scalar($value) ? null : [$path, get_debug_type($value)];
    }

    /**
     * The last config file (in merge order) that sets the value at $path to
     * something unexportable: that is the file whose value survived the merge.
     * Runs only on the error path, so loading the candidate files again is fine.
     *
     * @param array<int|string> $path Key path whose first element is the config file name
     * @param array<string> $modulePaths
     *
     * @throws ConfigLoadException
     */
    private function sourceFile(
        array $path,
        array $modulePaths,
    ): string {
        $name = (string) $path[0];
        $candidates = array_filter(
            $this->discovery->files($modulePaths, $this->projectPaths->config),
            fn (string $file): bool => pathinfo($file, PATHINFO_FILENAME) === $name,
        );
        $source = null;

        foreach ($candidates as $file) {
            $value = $this->loader->load($file);

            foreach (array_slice($path, 1) as $key) {
                $value = is_array($value) && array_key_exists($key, $value) ? $value[$key] : null;
            }

            if ($value !== null && !is_scalar($value) && !is_array($value)) {
                $source = $file;
            }
        }

        return $source ?? implode(' or ', $candidates);
    }
}
