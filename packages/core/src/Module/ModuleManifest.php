<?php

declare(strict_types=1);

namespace Marko\Core\Module;

use Closure;

/**
 * Value object representing a discovered module.
 *
 * Combines data from composer.json (name, version, require)
 * with Marko-specific config from module.php (enabled, sequence, bindings, boot, shutdown).
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
     * @param array<int|string, string|Closure> $singletons Shared interface to implementation bindings (from module.php).
     *   String keys bind interface => implementation; int keys are list-style entries naming a class to
     *   autowire as a singleton, which BindingRegistry::registerModule() distinguishes via is_int().
     * @param string $path Absolute path to module directory
     * @param string $source Discovery source: vendor, modules, or app
     * @param array<string, string> $autoload PSR-4 autoload configuration from composer.json (namespace => path)
     * @param Closure|null $boot Boot callback to run after bindings are registered (from module.php). Parameters are auto-injected from the container — type-hint any registered dependency, including ContainerInterface.
     * @param Closure|null $shutdown Shutdown callback run by Application::shutdown() (from module.php), in reverse module load order, to undo what the boot callback set up (global handlers, open resources). Parameters are auto-injected like boot's.
     * @param array<int, class-string> $globalMiddleware Global HTTP middleware classes declared by this module (from module.php). Order across modules follows DependencyResolver (composer require + sequence: { after, before }); order within a module follows array declaration order.
     * @param array<string, mixed> $extra Raw extra data from composer.json (vendor/Marko-specific metadata)
     * @param array<int, class-string> $discovery Discovery cache contributors declared by this module (from module.php); each must implement DiscoveryCacheContributorInterface
     * @param array<class-string, array<string, mixed>> $arguments Constructor argument values per concrete class, keyed by parameter name (from module.php)
     */
    public function __construct(
        public string $name,
        public string $version,
        public bool $enabled = true,
        public array $require = [],
        public array $after = [],
        public array $before = [],
        public array $bindings = [],
        public array $singletons = [],
        public string $path = '',
        public string $source = '',
        public array $autoload = [],
        public ?Closure $boot = null,
        public array $globalMiddleware = [],
        public array $extra = [],
        public array $discovery = [],
        public ?Closure $shutdown = null,
        public array $arguments = [],
    ) {}
}
