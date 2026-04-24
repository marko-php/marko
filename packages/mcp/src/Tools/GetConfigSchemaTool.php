<?php

declare(strict_types=1);

namespace Marko\Mcp\Tools;

use Marko\CodeIndexer\Cache\IndexCache;

readonly class GetConfigSchemaTool implements ToolHandlerInterface
{
    public function __construct(
        private IndexCache $index,
    ) {}

    public static function definition(IndexCache $index): ToolDefinition
    {
        return new ToolDefinition(
            name: 'get_config_schema',
            description: 'Returns all indexed configuration keys with their type, default value, and source location.',
            inputSchema: [
                'type' => 'object',
                'properties' => [],
            ],
            handler: new self($index),
        );
    }

    public function handle(array $arguments): array
    {
        $entries = $this->index->getConfigKeys();
        $rows = array_map(
            fn ($e) => "$e->key ($e->type) = " . var_export($e->defaultValue, true) . " ($e->file:$e->line)",
            $entries,
        );

        return ['content' => [['type' => 'text', 'text' => implode("\n", $rows) ?: '(no config keys indexed)']]];
    }
}
