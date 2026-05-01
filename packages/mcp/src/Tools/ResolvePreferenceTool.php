<?php

declare(strict_types=1);

namespace Marko\Mcp\Tools;

use Marko\CodeIndexer\Cache\IndexCache;

readonly class ResolvePreferenceTool implements ToolHandlerInterface
{
    public function __construct(private IndexCache $index) {}

    public static function definition(IndexCache $index): ToolDefinition
    {
        return new ToolDefinition(
            name: 'resolve_preference',
            description: 'Find which class is bound as a Preference for a given interface or class',
            inputSchema: [
                'type' => 'object',
                'properties' => ['class' => ['type' => 'string']],
                'required' => ['class'],
            ],
            handler: new self($index),
        );
    }

    public function handle(array $arguments): array
    {
        $target = (string) $arguments['class'];
        $match = null;

        foreach ($this->index->getPreferences() as $pref) {
            if ($pref->interface === $target) {
                $match = $pref;
                break;
            }
        }

        if ($match === null) {
            return ['content' => [['type' => 'text', 'text' => "No preference found for: $target"]]];
        }

        return ['content' => [['type' => 'text', 'text' => "$target → $match->implementation (module: $match->module)"]]];
    }
}
