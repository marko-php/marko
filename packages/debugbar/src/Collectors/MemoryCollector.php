<?php

declare(strict_types=1);

namespace Marko\Debugbar\Collectors;

use Marko\Debugbar\Debugbar;

class MemoryCollector implements CollectorInterface
{
    public function name(): string
    {
        return 'memory';
    }

    public function collect(Debugbar $debugbar): array
    {
        $current = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);

        return [
            'label' => 'Memory',
            'badge' => $this->formatBytes($peak),
            'start_bytes' => $debugbar->startMemory(),
            'current_bytes' => $current,
            'peak_bytes' => $peak,
            'current' => $this->formatBytes($current),
            'peak' => $this->formatBytes($peak),
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }
}
