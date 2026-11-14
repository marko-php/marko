<?php

declare(strict_types=1);

namespace Marko\Debugbar\Collectors;

use Marko\Debugbar\Debugbar;
use Marko\Debugbar\Support\Redactor;

class MessagesCollector implements CollectorInterface
{
    public function __construct(
        private readonly Redactor $redactor = new Redactor(),
    ) {}

    public function name(): string
    {
        return 'messages';
    }

    public function collect(
        Debugbar $debugbar,
    ): array {
        return [
            'label' => 'Messages',
            'badge' => count($debugbar->messages()),
            'messages' => array_map(
                fn ($message): array => $this->redactContext($message->toArray($debugbar->startTime())),
                $debugbar->messages(),
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
