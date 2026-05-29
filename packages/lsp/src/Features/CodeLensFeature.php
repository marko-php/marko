<?php

declare(strict_types=1);

namespace Marko\Lsp\Features;

use Marko\CodeIndexer\Cache\IndexCache;

readonly class CodeLensFeature
{
    public function __construct(private IndexCache $index) {}

    /**
     * Scan the document for `class ClassName` declarations.
     * For each class, emit lenses for observer count (if event class) and plugin count (if target class).
     *
     * @return list<array{range: array, command: array}>
     */
    public function lenses(string $documentText): array
    {
        $lenses = [];
        $lines = explode("\n", $documentText);
        $namespace = '';

        foreach ($lines as $lineNum => $line) {
            if (preg_match('/^\s*namespace\s+([\w\\\\]+)\s*;/', $line, $m)) {
                $namespace = $m[1];
                continue;
            }

            if (preg_match('/^\s*(?:abstract\s+|final\s+|readonly\s+)*class\s+(\w+)/', $line, $m)) {
                $shortName = $m[1];
                $fqcn = $namespace !== '' ? "$namespace\\$shortName" : $shortName;

                $observers = $this->index->findObserversForEvent($fqcn);
                $observerCount = count($observers);
                $lenses[] = [
                    'range' => [
                        'start' => ['line' => $lineNum, 'character' => 0],
                        'end' => ['line' => $lineNum, 'character' => 0],
                    ],
                    'command' => [
                        'title' => $observerCount . ' observer' . ($observerCount === 1 ? '' : 's') . ' listen',
                        'command' => 'marko.showObservers',
                        'arguments' => [$fqcn],
                    ],
                ];

                $plugins = $this->index->findPluginsForTarget($fqcn);
                $pluginCount = count($plugins);
                $lenses[] = [
                    'range' => [
                        'start' => ['line' => $lineNum, 'character' => 0],
                        'end' => ['line' => $lineNum, 'character' => 0],
                    ],
                    'command' => [
                        'title' => $pluginCount . ' plugin' . ($pluginCount === 1 ? '' : 's') . ' intercept',
                        'command' => 'marko.showPlugins',
                        'arguments' => [$fqcn],
                    ],
                ];
            }
        }

        return $lenses;
    }

    /**
     * Return locations for a class's observers (called when lens is clicked / executed).
     *
     * @return list<array{uri: string, range: array, label: string}>
     */
    public function resolveObservers(string $eventClass): array
    {
        $locations = [];

        foreach ($this->index->findObserversForEvent($eventClass) as $o) {
            $locations[] = [
                'uri' => 'class://' . $o->class,
                'range' => ['start' => ['line' => 0, 'character' => 0], 'end' => ['line' => 0, 'character' => 0]],
                'label' => "$o->class::$o->method (sortOrder: $o->sortOrder)",
            ];
        }

        return $locations;
    }

    /**
     * @return list<array{uri: string, range: array, label: string}>
     */
    public function resolvePlugins(string $targetClass): array
    {
        $locations = [];

        foreach ($this->index->findPluginsForTarget($targetClass) as $p) {
            $locations[] = [
                'uri' => 'class://' . $p->class,
                'range' => ['start' => ['line' => 0, 'character' => 0], 'end' => ['line' => 0, 'character' => 0]],
                'label' => "$p->class::$p->method [$p->type, sortOrder: $p->sortOrder]",
            ];
        }

        return $locations;
    }
}
