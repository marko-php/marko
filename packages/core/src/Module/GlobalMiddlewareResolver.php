<?php

declare(strict_types=1);

namespace Marko\Core\Module;

use Marko\Core\Exceptions\ModuleException;
use Marko\Routing\Middleware\MiddlewareInterface;

/**
 * Resolves global HTTP middleware from module declarations and built-in defaults.
 *
 * Merges module-declared globalMiddleware entries with built-in hardcoded
 * entries, sorts by priority (ascending = runs earlier), and deduplicates
 * using source priority (app > modules > vendor).
 */
class GlobalMiddlewareResolver
{
    /**
     * Default built-in global middleware with their priorities.
     *
     * These entries use skipIfMissing = true so apps without the optional
     * packages (page-cache, session, layout) continue to boot unchanged.
     *
     * @var array<int, array{class: string, priority: int, source: string, skipIfMissing: bool}>
     */
    public const array DEFAULT_BUILT_INS = [
        [
            'class' => 'Marko\\PageCache\\Middleware\\PageCacheMiddleware',
            'priority' => 10,
            'source' => 'vendor',
            'skipIfMissing' => true,
        ],
        [
            'class' => 'Marko\\Session\\Middleware\\SessionMiddleware',
            'priority' => 20,
            'source' => 'vendor',
            'skipIfMissing' => true,
        ],
        [
            'class' => 'Marko\\Layout\\Middleware\\LayoutMiddleware',
            'priority' => 30,
            'source' => 'vendor',
            'skipIfMissing' => true,
        ],
    ];

    private const array SOURCE_PRIORITY = [
        'vendor' => 0,
        'modules' => 1,
        'app' => 2,
    ];

    private const int DEFAULT_PRIORITY = 100;

    /**
     * Resolve global middleware from module declarations and built-in entries.
     *
     * @param array<ModuleManifest> $modules
     * @param array<int, array{class: string, priority: int, source: string, skipIfMissing?: bool}> $builtIns
     * @return array<class-string<MiddlewareInterface>>
     * @throws ModuleException When a module-declared class does not exist, is missing the class key, or does not implement MiddlewareInterface
     */
    public function resolve(
        array $modules,
        array $builtIns,
    ): array {
        // Candidate map: class => [priority, sourcePriority]
        // We keep the highest-source-priority entry; within same source, the lowest priority value.
        /** @var array<string, array{priority: int, sourcePriority: int}> $candidates */
        $candidates = [];

        // Process built-in entries first (lowest source priority = vendor)
        foreach ($builtIns as $builtIn) {
            $class = $builtIn['class'];
            $skipIfMissing = $builtIn['skipIfMissing'] ?? false;

            if (!class_exists($class)) {
                if ($skipIfMissing) {
                    continue;
                }
            }

            $sourcePriority = self::SOURCE_PRIORITY[$builtIn['source']] ?? 0;
            $this->addCandidate($candidates, $class, $builtIn['priority'], $sourcePriority);
        }

        // Process module-declared entries
        foreach ($modules as $module) {
            $sourcePriority = self::SOURCE_PRIORITY[$module->source] ?? 0;

            foreach ($module->globalMiddleware as $entry) {
                [$class, $priority] = $this->parseEntry($entry, $module->name);

                if (!class_exists($class)) {
                    throw ModuleException::invalidMiddlewareClass(
                        moduleName: $module->name,
                        className: $class,
                        reason: "Class '$class' does not exist",
                    );
                }

                if (!is_a($class, MiddlewareInterface::class, true)) {
                    throw ModuleException::invalidMiddlewareClass(
                        moduleName: $module->name,
                        className: $class,
                        reason: "Class '$class' does not implement " . MiddlewareInterface::class,
                    );
                }

                $this->addCandidate($candidates, $class, $priority, $sourcePriority);
            }
        }

        // Sort by priority ascending
        uasort($candidates, fn (array $a, array $b) => $a['priority'] <=> $b['priority']);

        return array_keys($candidates);
    }

    /**
     * Parse a single globalMiddleware entry (flat string or array form).
     *
     * @param mixed $entry
     * @return array{0: string, 1: int}
     * @throws ModuleException When entry is invalid
     */
    private function parseEntry(
        mixed $entry,
        string $moduleName,
    ): array {
        if (is_string($entry)) {
            return [$entry, self::DEFAULT_PRIORITY];
        }

        if (!is_array($entry) || !isset($entry['class'])) {
            throw ModuleException::invalidMiddlewareEntry(
                moduleName: $moduleName,
                reason: "Each globalMiddleware entry must be a class-string or an array with a 'class' key",
            );
        }

        return [$entry['class'], $entry['priority'] ?? self::DEFAULT_PRIORITY];
    }

    /**
     * Add or update a candidate entry applying deduplication rules.
     *
     * Rules:
     * - Higher source priority always wins (app > modules > vendor)
     * - Within the same source, keep the lowest priority value (runs earliest)
     *
     * @param array<string, array{priority: int, sourcePriority: int}> $candidates
     */
    private function addCandidate(
        array &$candidates,
        string $class,
        int $priority,
        int $sourcePriority,
    ): void {
        if (!isset($candidates[$class])) {
            $candidates[$class] = ['priority' => $priority, 'sourcePriority' => $sourcePriority];
            return;
        }

        $existing = $candidates[$class];

        if ($sourcePriority > $existing['sourcePriority']) {
            // Higher source priority wins unconditionally
            $candidates[$class] = ['priority' => $priority, 'sourcePriority' => $sourcePriority];
            return;
        }

        if ($sourcePriority === $existing['sourcePriority'] && $priority < $existing['priority']) {
            // Same source: keep the lower priority value (runs earlier)
            $candidates[$class]['priority'] = $priority;
        }
    }
}
