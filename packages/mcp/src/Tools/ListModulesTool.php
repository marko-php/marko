<?php

declare(strict_types=1);

namespace Marko\Mcp\Tools;

use Marko\CodeIndexer\Cache\IndexCache;
use Marko\CodeIndexer\ValueObject\ModuleInfo;

readonly class ListModulesTool implements ToolHandlerInterface
{
    public function __construct(private IndexCache $index) {}

    public static function definition(IndexCache $index): ToolDefinition
    {
        return new ToolDefinition(
            name: 'list_modules',
            description: 'List all Marko modules discovered in the project',
            inputSchema: [
                'type' => 'object',
                'properties' => ['filter' => ['type' => 'string', 'description' => 'Optional substring filter on module name']],
            ],
            handler: new self($index),
        );
    }

    public function handle(array $arguments): array
    {
        $filter = isset($arguments['filter']) ? (string) $arguments['filter'] : '';
        $modules = $this->index->getModules();

        if ($filter !== '') {
            $modules = array_values(
                array_filter($modules, fn (ModuleInfo $m) => str_contains($m->name, $filter)),
            );
        }

        $rows = array_map(fn (ModuleInfo $m) => "$m->name → $m->path", $modules);
        $text = implode("\n", $rows) ?: '(no modules found)';

        return ['content' => [['type' => 'text', 'text' => $text]]];
    }
}
