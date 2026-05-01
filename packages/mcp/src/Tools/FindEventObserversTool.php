<?php

declare(strict_types=1);

namespace Marko\Mcp\Tools;

use Marko\CodeIndexer\Cache\IndexCache;
use Marko\CodeIndexer\ValueObject\ObserverEntry;

readonly class FindEventObserversTool implements ToolHandlerInterface
{
    public function __construct(private IndexCache $index) {}

    public static function definition(IndexCache $index): ToolDefinition
    {
        return new ToolDefinition(
            name: 'find_event_observers',
            description: 'Find all observers listening to a given event class',
            inputSchema: [
                'type' => 'object',
                'properties' => ['event' => ['type' => 'string']],
                'required' => ['event'],
            ],
            handler: new self($index),
        );
    }

    public function handle(array $arguments): array
    {
        $event = (string) $arguments['event'];
        $observers = $this->index->findObserversForEvent($event);

        usort($observers, fn (ObserverEntry $a, ObserverEntry $b) => $a->sortOrder <=> $b->sortOrder);

        if ($observers === []) {
            return ['content' => [['type' => 'text', 'text' => "No observers found for event: $event"]]];
        }

        $rows = array_map(
            fn (ObserverEntry $o) => "$o->class::$o->method (sortOrder: $o->sortOrder)",
            $observers,
        );

        return ['content' => [['type' => 'text', 'text' => "Observers for $event:\n" . implode("\n", $rows)]]];
    }
}
