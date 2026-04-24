<?php

declare(strict_types=1);

namespace Marko\Mcp\Tools;

use Marko\CodeIndexer\Cache\IndexCache;
use Marko\CodeIndexer\ValueObject\RouteEntry;

readonly class ListRoutesTool implements ToolHandlerInterface
{
    public function __construct(private IndexCache $index) {}

    public static function definition(IndexCache $index): ToolDefinition
    {
        return new ToolDefinition(
            name: 'list_routes',
            description: 'List all Marko routes discovered in the project',
            inputSchema: [
                'type' => 'object',
                'properties' => ['filter' => ['type' => 'string', 'description' => 'Optional substring filter on path, class, or action']],
            ],
            handler: new self($index),
        );
    }

    public function handle(array $arguments): array
    {
        $filter = isset($arguments['filter']) ? (string) $arguments['filter'] : '';
        $routes = $this->index->getRoutes();

        if ($filter !== '') {
            $routes = array_values(
                array_filter(
                    $routes,
                    fn (RouteEntry $r) => str_contains($r->path, $filter)
                        || str_contains($r->class, $filter)
                        || str_contains($r->action, $filter),
                ),
            );
        }

        $rows = array_map(
            fn (RouteEntry $r) => "$r->method $r->path → $r->class::$r->action",
            $routes,
        );
        $text = implode("\n", $rows) ?: '(no routes found)';

        return ['content' => [['type' => 'text', 'text' => $text]]];
    }
}
