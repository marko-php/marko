<?php

declare(strict_types=1);

namespace Marko\Debugbar\Collectors;

use Marko\Debugbar\Debugbar;

class DatabaseCollector implements CollectorInterface
{
    public function name(): string
    {
        return 'database';
    }

    public function collect(Debugbar $debugbar): array
    {
        $queries = $debugbar->queries();
        $duration = array_reduce(
            $queries,
            static fn (float $total, $query): float => $total + $query->durationMs,
            0.0,
        );
        $withBindings = $debugbar->configBool('debugbar.options.database.with_bindings', true);

        return [
            'label' => 'Database',
            'badge' => count($queries),
            'count' => count($queries),
            'duration_ms' => round($duration, 2),
            'slow_threshold_ms' => $debugbar->configFloat('debugbar.options.database.slow_threshold_ms', 100.0),
            'queries' => array_map(
                static fn ($query): array => $query->toArray($debugbar->startTime(), $withBindings),
                $queries,
            ),
        ];
    }
}
