<?php

declare(strict_types=1);

namespace Marko\Core\Discovery;

use Marko\Core\Command\CommandDefinition;
use Marko\Core\Command\CommandDiscovery;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Container\PreferenceDiscovery;
use Marko\Core\Container\PreferenceRecord;
use Marko\Core\Event\ObserverDefinition;
use Marko\Core\Event\ObserverDiscovery;
use Marko\Core\Exceptions\DiscoveryCacheException;
use Marko\Core\Exceptions\ModuleException;
use Marko\Core\Module\GlobalMiddlewareResolver;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Plugin\PluginDefinition;
use Marko\Core\Plugin\PluginDiscovery;
use Marko\Routing\Middleware\MiddlewareInterface;
use Psr\Container\ContainerExceptionInterface;

/**
 * Runs every discovery pass over a resolved module list and returns the
 * payload that DiscoveryCache::write() consumes: the module list itself,
 * preferences, plugins, observers, commands, the global middleware order,
 * and one section per DiscoveryCacheContributorInterface declared by a
 * module's module.php 'discovery' key.
 *
 * The four core attribute passes construct their discovery classes directly
 * with `new` (no #[Preference] targets them, and discovery runs before
 * preferences apply). Contributors are resolved through the container so a
 * package's contributor can inject what it needs.
 */
readonly class DiscoveryCompiler
{
    public function __construct(
        private ContainerInterface $container,
    ) {}

    /**
     * Compile a cache payload by running every discovery pass over the given modules.
     *
     * @param array<ModuleManifest> $modules Enabled modules in load order
     * @return array{version: int, modules: array<ModuleManifest>, preferences: PreferenceRecord[], plugins: PluginDefinition[], observers: ObserverDefinition[], commands: CommandDefinition[], globalMiddleware: array<int, string>, sections: array<string, array<mixed>>}
     *
     * @throws DiscoveryCacheException|ModuleException|ContainerExceptionInterface
     */
    public function compile(
        array $modules,
    ): array {
        return [
            'version' => DiscoveryCache::CACHE_VERSION,
            'modules' => array_values($modules),
            'preferences' => $this->runPreferenceDiscovery($modules),
            'plugins' => $this->runPluginDiscovery($modules),
            'observers' => $this->runObserverDiscovery($modules),
            'commands' => $this->runCommandDiscovery($modules),
            'globalMiddleware' => $this->resolveGlobalMiddleware($modules),
            'sections' => $this->runContributors($modules),
        ];
    }

    /**
     * @param array<ModuleManifest> $modules
     * @return PreferenceRecord[]
     */
    private function runPreferenceDiscovery(
        array $modules,
    ): array {
        $discovery = new PreferenceDiscovery();
        $records = [];

        foreach ($modules as $module) {
            $records = array_merge($records, $discovery->discoverInModule($module));
        }

        return $records;
    }

    /**
     * @param array<ModuleManifest> $modules
     * @return PluginDefinition[]
     */
    private function runPluginDiscovery(
        array $modules,
    ): array {
        $discovery = new PluginDiscovery();
        $definitions = [];

        foreach ($modules as $module) {
            $definitions = array_merge($definitions, $discovery->discoverInModule($module));
        }

        return $definitions;
    }

    /**
     * @param array<ModuleManifest> $modules
     * @return ObserverDefinition[]
     */
    private function runObserverDiscovery(
        array $modules,
    ): array {
        $discovery = new ObserverDiscovery(new ClassFileParser());

        return $discovery->discover($modules);
    }

    /**
     * @param array<ModuleManifest> $modules
     * @return CommandDefinition[]
     */
    private function runCommandDiscovery(
        array $modules,
    ): array {
        $discovery = new CommandDiscovery(new ClassFileParser());

        return $discovery->discover($modules);
    }

    /**
     * Global middleware only exists when marko/routing is installed, matching
     * the live boot path in Application.
     *
     * @param array<ModuleManifest> $modules
     * @return array<int, string>
     *
     * @throws ModuleException
     */
    private function resolveGlobalMiddleware(
        array $modules,
    ): array {
        if (!interface_exists(MiddlewareInterface::class)) {
            return [];
        }

        return new GlobalMiddlewareResolver()->resolve($modules);
    }

    /**
     * @param array<ModuleManifest> $modules
     * @return array<string, array<mixed>>
     *
     * @throws DiscoveryCacheException|ContainerExceptionInterface
     */
    private function runContributors(
        array $modules,
    ): array {
        $sections = [];

        /** @var array<string, string> $owners section key => contributor class */
        $owners = [];

        foreach ($modules as $module) {
            foreach ($module->discovery as $className) {
                $contributor = $this->resolveContributor($module, $className);
                $key = $contributor->key();

                if (isset($owners[$key])) {
                    throw DiscoveryCacheException::duplicateContributorKey($key, $owners[$key], $className);
                }

                $data = $contributor->compile($modules);
                $this->assertExportable($data, $key, $className, $key);

                $owners[$key] = $className;
                $sections[$key] = $data;
            }
        }

        return $sections;
    }

    /**
     * @throws DiscoveryCacheException|ContainerExceptionInterface
     */
    private function resolveContributor(
        ModuleManifest $module,
        mixed $className,
    ): DiscoveryCacheContributorInterface {
        if (
            !is_string($className)
            || !class_exists($className)
        ) {
            throw DiscoveryCacheException::invalidContributor(
                $module->name,
                is_string($className) ? $className : get_debug_type($className),
                'the class does not exist',
            );
        }

        if (!is_a($className, DiscoveryCacheContributorInterface::class, true)) {
            throw DiscoveryCacheException::invalidContributor(
                $module->name,
                $className,
                'it does not implement ' . DiscoveryCacheContributorInterface::class,
            );
        }

        /** @var DiscoveryCacheContributorInterface */
        return $this->container->get($className);
    }

    /**
     * Only scalars, null and arrays survive var_export() as plain data.
     *
     * @throws DiscoveryCacheException
     */
    private function assertExportable(
        mixed $value,
        string $key,
        string $className,
        string $location,
    ): void {
        if (is_array($value)) {
            foreach ($value as $index => $item) {
                $this->assertExportable($item, $key, $className, "$location.$index");
            }

            return;
        }

        if (
            $value === null
            || is_scalar($value)
        ) {
            return;
        }

        throw DiscoveryCacheException::unexportableSection(
            $key,
            $className,
            "$location is a " . get_debug_type($value),
        );
    }
}
