<?php

declare(strict_types=1);

namespace Marko\Core\Module;

use Marko\Core\Exceptions\CircularDependencyException;
use Marko\Core\Exceptions\ModuleException;

class DependencyResolver
{
    /**
     * Resolve module dependencies and return modules in load order.
     *
     * Uses Kahn's algorithm for topological sorting.
     *
     * @param ModuleManifest[] $modules
     * @return ModuleManifest[]
     * @throws ModuleException When a required module dependency is not found
     * @throws CircularDependencyException When modules have circular dependencies
     */
    public function resolve(array $modules): array
    {
        // Filter out disabled modules first
        $enabledModules = array_filter($modules, fn (ModuleManifest $m) => $m->enabled);

        // Index modules by name (only enabled ones)
        $modulesByName = [];
        foreach ($enabledModules as $module) {
            $modulesByName[$module->name] = $module;
        }

        // Validate that all required dependencies exist (and are enabled)
        foreach ($enabledModules as $module) {
            foreach (array_keys($module->require) as $dependency) {
                if (!isset($modulesByName[$dependency])) {
                    throw ModuleException::missingDependency($module->name, $dependency);
                }
            }
        }

        // Build adjacency list and in-degree count
        // Edge from A -> B means A must be loaded before B
        $inDegree = [];
        $dependents = []; // dependents[A] = [B, C] means B and C depend on A

        foreach ($enabledModules as $module) {
            $inDegree[$module->name] ??= 0;
            $dependents[$module->name] ??= [];

            // Extract dependency names from require (associative array)
            $dependencies = array_keys($module->require);

            foreach ($dependencies as $dependency) {
                // Only consider dependencies that are in our module list
                if (isset($modulesByName[$dependency])) {
                    $inDegree[$module->name]++;
                    $dependents[$dependency][] = $module->name;
                }
            }

            // Handle sequence 'after' hints (soft ordering)
            // If module wants to load after X, X must load before this module
            foreach ($module->after as $afterModule) {
                if (isset($modulesByName[$afterModule])) {
                    $inDegree[$module->name]++;
                    $dependents[$afterModule][] = $module->name;
                }
            }

            // Handle sequence 'before' hints (soft ordering)
            // If module wants to load before X, this module must load before X
            foreach ($module->before as $beforeModule) {
                if (isset($modulesByName[$beforeModule])) {
                    $inDegree[$beforeModule] ??= 0;
                    $inDegree[$beforeModule]++;
                    $dependents[$module->name][] = $beforeModule;
                }
            }
        }

        // Kahn's algorithm: start with modules that have no dependencies
        $queue = [];
        foreach ($enabledModules as $module) {
            if ($inDegree[$module->name] === 0) {
                $queue[] = $module->name;
            }
        }

        $sorted = [];
        while (!empty($queue)) {
            $current = array_shift($queue);
            $sorted[] = $modulesByName[$current];

            foreach ($dependents[$current] as $dependent) {
                $inDegree[$dependent]--;
                if ($inDegree[$dependent] === 0) {
                    $queue[] = $dependent;
                }
            }
        }

        // Detect circular dependency: if not all enabled modules are sorted
        if (count($sorted) !== count($enabledModules)) {
            $cycle = $this->findCycle($enabledModules, $dependents);
            throw CircularDependencyException::detected($cycle);
        }

        return $sorted;
    }

    /**
     * Find a cycle in the dependency graph using DFS.
     *
     * @param ModuleManifest[] $modules
     * @param array<string, string[]> $dependents
     * @return string[]
     */
    private function findCycle(
        array $modules,
        array $dependents,
    ): array {
        $visited = [];
        $recursionStack = [];
        $path = [];

        foreach ($modules as $module) {
            if ($this->detectCycleDfs($module->name, $dependents, $visited, $recursionStack, $path)) {
                return $path;
            }
        }

        return [];
    }

    /**
     * @param array<string, string[]> $dependents
     * @param array<string, bool> $visited
     * @param array<string, bool> $recursionStack
     * @param string[] $path
     */
    private function detectCycleDfs(
        string $node,
        array $dependents,
        array &$visited,
        array &$recursionStack,
        array &$path,
    ): bool {
        $visited[$node] = true;
        $recursionStack[$node] = true;
        $path[] = $node;

        foreach ($dependents[$node] ?? [] as $dependent) {
            if (!isset($visited[$dependent])) {
                if ($this->detectCycleDfs($dependent, $dependents, $visited, $recursionStack, $path)) {
                    return true;
                }
            } elseif (isset($recursionStack[$dependent])) {
                // Found cycle, trim path to start at the cycle
                $cycleStart = array_search($dependent, $path);
                $path = array_slice($path, $cycleStart);
                $path[] = $dependent; // Add to show the cycle completes

                return true;
            }
        }

        array_pop($path);
        unset($recursionStack[$node]);

        return false;
    }
}
