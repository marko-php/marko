<?php

declare(strict_types=1);

namespace Marko\Config;

use Marko\Core\Container\BindingRegistry;
use Marko\Core\Module\ModuleManifest;

/**
 * Orders module paths for config merging: source tier first (vendor, then
 * modules/, then app/), module load order within a tier. A later path
 * overrides an earlier one, so app config beats vendor config.
 */
readonly class ConfigModuleOrder
{
    /**
     * @param array<ModuleManifest> $modules In module load order
     * @return list<string> Module paths, lowest priority first
     */
    public function paths(
        array $modules,
    ): array {
        $modules = array_values($modules);

        usort(
            $modules,
            fn (ModuleManifest $a, ModuleManifest $b): int => (BindingRegistry::SOURCE_PRIORITY[$a->source] ?? 0)
                <=> (BindingRegistry::SOURCE_PRIORITY[$b->source] ?? 0),
        );

        return array_map(fn (ModuleManifest $module): string => $module->path, $modules);
    }
}
