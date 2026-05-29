<?php

declare(strict_types=1);

namespace Marko\Mcp\Tools;

use Marko\CodeIndexer\Cache\IndexCache;
use Marko\CodeIndexer\ValueObject\CommandEntry;

readonly class ListCommandsTool implements ToolHandlerInterface
{
    public function __construct(private IndexCache $index) {}

    public static function definition(IndexCache $index): ToolDefinition
    {
        return new ToolDefinition(
            name: 'list_commands',
            description: 'List all Marko console commands discovered in the project',
            inputSchema: [
                'type' => 'object',
                'properties' => ['filter' => ['type' => 'string', 'description' => 'Optional substring filter on command name or class']],
            ],
            handler: new self($index),
        );
    }

    public function handle(array $arguments): array
    {
        $filter = isset($arguments['filter']) ? (string) $arguments['filter'] : '';
        $commands = $this->index->getCommands();

        if ($filter !== '') {
            $commands = array_values(
                array_filter(
                    $commands,
                    fn (CommandEntry $c) => str_contains($c->name, $filter) || str_contains($c->class, $filter),
                ),
            );
        }

        $rows = array_map(fn (CommandEntry $c) => "$c->name ($c->class)", $commands);
        $text = implode("\n", $rows) ?: '(no commands found)';

        return ['content' => [['type' => 'text', 'text' => $text]]];
    }
}
