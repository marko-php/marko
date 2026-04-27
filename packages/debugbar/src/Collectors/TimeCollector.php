<?php

declare(strict_types=1);

namespace Marko\Debugbar\Collectors;

use Marko\Debugbar\Debugbar;

class TimeCollector implements CollectorInterface
{
    public function name(): string
    {
        return 'time';
    }

    public function collect(Debugbar $debugbar): array
    {
        return [
            'label' => 'Time',
            'badge' => $debugbar->durationMs().' ms',
            'duration_ms' => $debugbar->durationMs(),
            'measures' => array_map(
                static fn ($measure): array => $measure->toArray($debugbar->startTime()),
                $debugbar->measures(),
            ),
        ];
    }
}
