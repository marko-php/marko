<?php

declare(strict_types=1);

namespace Marko\Debugbar\Collectors;

use Marko\Debugbar\Debugbar;

class ViewCollector implements CollectorInterface
{
    public function name(): string
    {
        return 'views';
    }

    public function collect(Debugbar $debugbar): array
    {
        $renders = [];
        $total = 0.0;

        foreach ($debugbar->viewRenders() as $render) {
            $total += $render->durationMs;
            $renders[] = [
                'method' => $render->method,
                'template' => $render->template,
                'data_keys' => $render->dataKeys,
                'start_ms' => round(($render->start - $debugbar->startTime()) * 1000, 2),
                'duration_ms' => $render->durationMs,
                'output_size' => $render->outputSize,
            ];
        }

        return [
            'label' => 'Views',
            'badge' => (string) count($renders),
            'count' => count($renders),
            'duration_ms' => round($total, 2),
            'renders' => $renders,
        ];
    }
}
