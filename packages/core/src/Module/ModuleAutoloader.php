<?php

declare(strict_types=1);

namespace Marko\Core\Module;

use Marko\Core\Exceptions\ModuleException;

/**
 * Registers PSR-4 autoloaders for non-vendor Marko modules.
 *
 * Discovers modules in modules/ and app/ directories, then registers
 * spl_autoload closures so their classes can be loaded without a full
 * Application boot. Vendor modules are skipped — Composer already handles them.
 *
 * Registration is idempotent: the same namespace+path combination will not
 * be registered twice even if register() is called multiple times.
 */
class ModuleAutoloader
{
    /** @var array<string, true> Tracks registered namespace+path pairs to prevent duplicates */
    private array $registered = [];

    public function __construct(
        private readonly string $modulesPath,
        private readonly string $appPath,
        private readonly ManifestParser $parser,
    ) {}

    /**
     * Discover app and modules-dir modules and register a PSR-4 autoloader for each.
     *
     * Safe to call multiple times; duplicate namespace+path pairs are silently skipped.
     *
     * @throws ModuleException
     */
    public function register(): void
    {
        $discovery = new ModuleDiscovery($this->parser);

        $modules = array_merge(
            $discovery->discoverInModules($this->modulesPath),
            $discovery->discoverInApp($this->appPath),
        );

        foreach ($modules as $module) {
            foreach ($module->autoload as $namespace => $path) {
                $absolutePath = $module->path . '/' . rtrim($path, '/');
                $key = $namespace . ':' . $absolutePath;

                if (isset($this->registered[$key])) {
                    continue;
                }

                $this->registered[$key] = true;
                $this->registerPsr4($namespace, $absolutePath);
            }
        }
    }

    private function registerPsr4(string $namespace, string $basePath): void
    {
        spl_autoload_register(function (string $class) use ($namespace, $basePath): void {
            if (!str_starts_with($class, $namespace)) {
                return;
            }

            $relativeClass = substr($class, strlen($namespace));
            $file = $basePath . '/' . str_replace('\\', '/', $relativeClass) . '.php';

            if (is_file($file)) {
                require_once $file;
            }
        });
    }
}
