<?php

declare(strict_types=1);

namespace Marko\Mcp\Tools;

use Marko\CodeIndexer\Cache\IndexCache;
use Marko\CodeIndexer\ValueObject\PluginEntry;

readonly class FindPluginsTargetingTool implements ToolHandlerInterface
{
    public function __construct(private IndexCache $index) {}

    public static function definition(IndexCache $index): ToolDefinition
    {
        return new ToolDefinition(
            name: 'find_plugins_targeting',
            description: 'Find all plugins targeting a given class',
            inputSchema: [
                'type' => 'object',
                'properties' => ['target' => ['type' => 'string']],
                'required' => ['target'],
            ],
            handler: new self($index),
        );
    }

    public function handle(array $arguments): array
    {
        $target = (string) $arguments['target'];
        $plugins = $this->index->findPluginsForTarget($target);

        usort($plugins, fn (PluginEntry $a, PluginEntry $b) => $a->sortOrder <=> $b->sortOrder);

        if ($plugins === []) {
            return ['content' => [['type' => 'text', 'text' => "No plugins found for: $target"]]];
        }

        $rows = array_map(
            fn (PluginEntry $p) => "$p->class::$p->method [$p->type, sortOrder: $p->sortOrder]",
            $plugins,
        );

        return ['content' => [['type' => 'text', 'text' => "Plugins targeting $target:\n" . implode("\n", $rows)]]];
    }
}
