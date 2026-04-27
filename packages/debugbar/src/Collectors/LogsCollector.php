<?php

declare(strict_types=1);

namespace Marko\Debugbar\Collectors;

use Marko\Debugbar\Debugbar;

class LogsCollector implements CollectorInterface
{
    public function name(): string
    {
        return 'logs';
    }

    public function collect(Debugbar $debugbar): array
    {
        return [
            'label' => 'Logs',
            'badge' => count($debugbar->logs()),
            'logs' => array_map(
                static fn ($log): array => $log->toArray($debugbar->startTime()),
                $debugbar->logs(),
            ),
        ];
    }
}
