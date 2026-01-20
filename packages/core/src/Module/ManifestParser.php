<?php

declare(strict_types=1);

namespace Marko\Core\Module;

use Marko\Core\Exceptions\ModuleException;
use ParseError;

/**
 * Parses module configuration from composer.json and module.php files.
 *
 * composer.json provides: name, version, require (standard Composer metadata)
 * module.php provides: enabled, sequence, bindings (Marko-specific config)
 */
class ManifestParser
{
    /**
     * Parse a module directory containing composer.json and optionally module.php.
     *
     * @param string $modulePath Path to the module directory
     * @throws ModuleException If composer.json is missing or invalid
     */
    public function parse(
        string $modulePath,
    ): ModuleManifest {
        $composerData = $this->parseComposerJson($modulePath);
        $moduleData = $this->parseModulePhp($modulePath);

        $sequence = $moduleData['sequence'] ?? [];

        return new ModuleManifest(
            name: $composerData['name'],
            version: $composerData['version'] ?? '1.0.0',
            enabled: $moduleData['enabled'] ?? true,
            require: $this->extractMarkoRequirements($composerData['require'] ?? []),
            after: $sequence['after'] ?? [],
            before: $sequence['before'] ?? [],
            bindings: $moduleData['bindings'] ?? [],
            autoload: $composerData['autoload']['psr-4'] ?? [],
        );
    }

    /**
     * Parse composer.json file (required).
     *
     * @return array<string, mixed>
     * @throws ModuleException If composer.json is missing or invalid
     */
    private function parseComposerJson(
        string $modulePath,
    ): array {
        $composerPath = $modulePath . '/composer.json';

        if (!is_file($composerPath)) {
            throw ModuleException::invalidManifest(
                basename($modulePath),
                'Missing required composer.json file',
            );
        }

        $content = file_get_contents($composerPath);

        if ($content === false) {
            throw ModuleException::invalidManifest(
                basename($modulePath),
                'Unable to read composer.json file',
            );
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw ModuleException::invalidManifest(
                basename($modulePath),
                'Invalid JSON in composer.json: ' . json_last_error_msg(),
            );
        }

        if (!isset($data['name']) || !is_string($data['name'])) {
            throw ModuleException::invalidManifest(
                basename($modulePath),
                "Missing required 'name' field in composer.json",
            );
        }

        return $data;
    }

    /**
     * Parse module.php file (optional, provides Marko-specific config).
     *
     * @return array<string, mixed>
     * @throws ModuleException If module.php exists but has syntax errors
     */
    private function parseModulePhp(
        string $modulePath,
    ): array {
        $modulePhpPath = $modulePath . '/module.php';

        if (!is_file($modulePhpPath)) {
            return []; // module.php is optional
        }

        try {
            $data = require $modulePhpPath;
        } catch (ParseError $e) {
            throw ModuleException::invalidManifest(
                basename($modulePath),
                "PHP syntax error in module.php: {$e->getMessage()}",
            );
        }

        if (!is_array($data)) {
            throw ModuleException::invalidManifest(
                basename($modulePath),
                'module.php must return an array',
            );
        }

        return $data;
    }

    /**
     * Extract only Marko module dependencies from composer require.
     *
     * Filters out non-module dependencies (php, ext-*, etc.)
     *
     * @param array<string, string> $require
     * @return array<string, string>
     */
    private function extractMarkoRequirements(
        array $require,
    ): array {
        return array_filter(
            $require,
            fn (string $package) => !str_starts_with($package, 'php')
                && !str_starts_with($package, 'ext-')
                && !str_starts_with($package, 'lib-'),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
