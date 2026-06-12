<?php

declare(strict_types=1);

namespace Marko\Core\Discovery;

use Marko\Core\Command\CommandDefinition;
use Marko\Core\Command\CommandDiscovery;
use Marko\Core\Container\PreferenceDiscovery;
use Marko\Core\Container\PreferenceRecord;
use Marko\Core\Event\ObserverDefinition;
use Marko\Core\Event\ObserverDiscovery;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Plugin\PluginDefinition;
use Marko\Core\Plugin\PluginDiscovery;

/**
 * Runs the four discovery passes over a resolved module list and returns the
 * plain-array payload that DiscoveryCache::write() consumes.
 *
 * This class intentionally bypasses container/preference resolution for the
 * discovery classes themselves (ObserverDiscovery, CommandDiscovery etc.) and
 * constructs them directly with `new`. This is safe because:
 *  1. Discovery runs before preferences apply to discovery classes.
 *  2. No #[Preference] currently targets any of the four discovery classes.
 * The equivalence test guards this invariant: compiled output must equal a
 * fresh scan performed by the same construction the Application uses.
 */
class DiscoveryCompiler
{
    /**
     * Compile a cache payload by running all four discovery passes over the given modules.
     *
     * @param array<ModuleManifest> $modules
     * @return array{version: int, preferences: PreferenceRecord[], plugins: PluginDefinition[], observers: ObserverDefinition[], commands: CommandDefinition[]}
     */
    public function compile(array $modules): array
    {
        $preferences = $this->runPreferenceDiscovery($modules);
        $plugins = $this->runPluginDiscovery($modules);
        $observers = $this->runObserverDiscovery($modules);
        $commands = $this->runCommandDiscovery($modules);

        return [
            'version' => DiscoveryCache::CACHE_VERSION,
            'preferences' => $preferences,
            'plugins' => $plugins,
            'observers' => $observers,
            'commands' => $commands,
        ];
    }

    /**
     * @param array<ModuleManifest> $modules
     * @return PreferenceRecord[]
     */
    private function runPreferenceDiscovery(array $modules): array
    {
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
    private function runPluginDiscovery(array $modules): array
    {
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
    private function runObserverDiscovery(array $modules): array
    {
        $discovery = new ObserverDiscovery(new ClassFileParser());

        return $discovery->discover($modules);
    }

    /**
     * @param array<ModuleManifest> $modules
     * @return CommandDefinition[]
     */
    private function runCommandDiscovery(array $modules): array
    {
        $discovery = new CommandDiscovery(new ClassFileParser());

        return $discovery->discover($modules);
    }
}
