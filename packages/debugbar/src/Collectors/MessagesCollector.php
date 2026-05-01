<?php

declare(strict_types=1);

namespace Marko\Debugbar\Collectors;

use Marko\Debugbar\Debugbar;

class MessagesCollector implements CollectorInterface
{
    public function name(): string
    {
        return 'messages';
    }

    public function collect(Debugbar $debugbar): array
    {
        return [
            'label' => 'Messages',
            'badge' => count($debugbar->messages()),
            'messages' => array_map(
                static fn ($message): array => $message->toArray($debugbar->startTime()),
                $debugbar->messages(),
            ),
        ];
    }
}
