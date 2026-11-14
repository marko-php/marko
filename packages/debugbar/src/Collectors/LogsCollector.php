<?php

declare(strict_types=1);

namespace Marko\Debugbar\Collectors;

use Marko\Debugbar\Debugbar;
use Marko\Debugbar\Support\Redactor;

class LogsCollector implements CollectorInterface
{
    public function __construct(
        private readonly Redactor $redactor = new Redactor(),
    ) {}

    public function name(): string
    {
        return 'logs';
    }

    public function collect(
        Debugbar $debugbar,
    ): array {
        return [
            'label' => 'Logs',
            'badge' => count($debugbar->logs()),
            'logs' => array_map(
                fn ($log): array => $this->redactContext($log->toArray($debugbar->startTime())),
                $debugbar->logs(),
            ),
        ];
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function redactContext(
        array $entry,
    ): array {
        if (is_array($entry['context'] ?? null)) {
            $entry['context'] = $this->redactor->redact($entry['context']);
        }

        return $entry;
    }
}
