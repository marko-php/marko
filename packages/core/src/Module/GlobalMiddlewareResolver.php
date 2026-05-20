<?php

declare(strict_types=1);

namespace Marko\Core\Module;

use Marko\Core\Exceptions\ModuleException;
use Marko\Routing\Middleware\MiddlewareInterface;

/**
 * Resolves global HTTP middleware from module declarations.
 *
 * Collects globalMiddleware entries from all loaded modules, sorts by priority
 * (ascending = runs earlier), and deduplicates using source priority
 * (app > modules > vendor).
 */
class GlobalMiddlewareResolver
{
    private const array SOURCE_PRIORITY = [
        'vendor' => 0,
        'modules' => 1,
        'app' => 2,
    ];

    private const int DEFAULT_PRIORITY = 100;

    /**
     * Resolve global middleware from module declarations.
     *
     * @param array<ModuleManifest> $modules
     * @return array<class-string<MiddlewareInterface>>
     * @throws ModuleException When a module-declared class does not exist, is missing the class key, or does not implement MiddlewareInterface
     */
    public function resolve(array $modules): array
    {
        // Candidate map: class => [priority, sourcePriority]
        // We keep the highest-source-priority entry; within same source, the lowest priority value.
        /** @var array<string, array{priority: int, sourcePriority: int}> $candidates */
        $candidates = [];

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
