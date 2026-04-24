<?php

declare(strict_types=1);

namespace Marko\Mcp\Tools;

use Marko\CodeIndexer\Cache\IndexCache;

readonly class CheckConfigKeyTool implements ToolHandlerInterface
{
    public function __construct(
        private IndexCache $index,
    ) {}

    public static function definition(IndexCache $index): ToolDefinition
    {
        return new ToolDefinition(
            name: 'check_config_key',
            description: 'Checks whether a configuration key exists in the index. Returns metadata for known keys or closest-match suggestions for unknown ones.',
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'key' => ['type' => 'string', 'description' => 'The configuration key to look up'],
                ],
                'required' => ['key'],
            ],
            handler: new self($index),
        );
    }

    public function handle(array $arguments): array
    {
        $key = (string) $arguments['key'];

        foreach ($this->index->getConfigKeys() as $e) {
            if ($e->key === $key) {
                $defaultStr = var_export($e->defaultValue, true);

                return ['content' => [['type' => 'text', 'text' => implode("\n", [
                    'exists: true',
                    "key: $key",
                    "type: $e->type",
                    "default: $defaultStr",
                    "file: $e->file:$e->line",
                    "module: $e->module",
                ])]]];
            }
        }

        $candidates = array_map(fn ($e) => $e->key, $this->index->getConfigKeys());
        $suggestions = $this->closestMatches($key, $candidates, 3);

        $lines = ['exists: false', "key: $key"];

        if ($suggestions !== []) {
            $lines[] = 'suggestions: ' . implode(', ', $suggestions);
        }

        return ['content' => [['type' => 'text', 'text' => implode("\n", $lines)]]];
    }

    /**
     * @param list<string> $candidates
     * @return list<string>
     */
    private function closestMatches(string $needle, array $candidates, int $max): array
    {
        $scored = array_map(fn ($c) => ['key' => $c, 'distance' => levenshtein($needle, $c)], $candidates);
        usort($scored, fn ($a, $b) => $a['distance'] <=> $b['distance']);

        return array_map(fn ($s) => $s['key'], array_slice($scored, 0, $max));
    }
}
