<?php

declare(strict_types=1);

namespace Marko\Core\Module;

use Closure;

/**
 * Value object representing a discovered module.
 *
 * Combines data from composer.json (name, version, require)
 * with Marko-specific config from module.php (enabled, sequence, bindings, boot).
 */
readonly class ModuleManifest
{
    /**
     * @param string $name Package name from composer.json (vendor/package format)
     * @param string $version Package version from composer.json
     * @param bool $enabled Whether module is enabled (from module.php, default true)
     * @param array<string, string> $require Hard dependencies from composer.json (package => version constraint)
     * @param array<string> $after Modules to load before this one (from module.php)
     * @param array<string> $before Modules to load after this one (from module.php)
     * @param array<string, string|Closure> $bindings Interface to implementation bindings (from module.php)
     * @param string $path Absolute path to module directory
     * @param string $source Discovery source: vendor, modules, or app
     * @param array<string, string> $autoload PSR-4 autoload configuration from composer.json (namespace => path)
     * @param Closure|null $boot Boot callback to run after bindings are registered (from module.php)
     */
    public function __construct(
        public string $name,
        public string $version,
        public bool $enabled = true,
        public array $require = [],
        public array $after = [],
        public array $before = [],
        public array $bindings = [],
        public string $path = '',
        public string $source = '',
        public array $autoload = [],
        public ?Closure $boot = null,
    ) {}
}
