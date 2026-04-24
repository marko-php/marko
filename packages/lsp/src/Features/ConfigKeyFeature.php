<?php

declare(strict_types=1);

namespace Marko\Lsp\Features;

use Marko\CodeIndexer\Cache\IndexCache;
use Marko\CodeIndexer\ValueObject\ConfigKeyEntry;

readonly class ConfigKeyFeature
{
    private const array CONFIG_GETTER_METHODS = ['get', 'getString', 'getInt', 'getBool', 'getFloat', 'getArray'];

    public function __construct(private IndexCache $index) {}

    /**
     * Detects whether the cursor is inside a config getter string literal.
     * Returns the partial key typed so far, or null if not in such context.
     */
    public function detectContext(string $lineText, int $col): ?string
    {
        $prefix = substr($lineText, 0, $col);
        $pattern = '/->\s*(' . implode('|', self::CONFIG_GETTER_METHODS) . ')\s*\(\s*[\'"]([^\'"]*)$/';

        if (preg_match($pattern, $prefix, $m)) {
            return $m[2];
        }

        return null;
    }

    /**
     * Returns LSP completion items.
     *
     * @return list<array{label: string, kind: int, detail: string, documentation: string, insertText: string}>
     */
    public function complete(string $partial): array
    {
        $items = [];

        foreach ($this->index->getConfigKeys() as $entry) {
            if ($partial !== '' && !str_starts_with($entry->key, $partial)) {
                continue;
            }

            $items[] = [
                'label' => $entry->key,
                'kind' => 14,
                'detail' => $entry->type . ' = ' . var_export($entry->defaultValue, true),
                'documentation' => "From module: $entry->module\nFile: $entry->file:$entry->line",
                'insertText' => $entry->key,
            ];
        }

        return $items;
    }

    /**
     * @return ?array{uri: string, range: array}
     */
    public function gotoDefinition(string $key): ?array
    {
        foreach ($this->index->getConfigKeys() as $entry) {
            if ($entry->key === $key) {
                return [
                    'uri' => 'file://' . $entry->file,
                    'range' => [
                        'start' => ['line' => max(0, $entry->line - 1), 'character' => 0],
                        'end' => ['line' => max(0, $entry->line - 1), 'character' => 0],
                    ],
                ];
            }
        }

        return null;
    }

    /**
     * Returns diagnostics for unknown config keys in the document.
     *
     * @return list<array{range: array, severity: int, message: string, code: string}>
     */
    public function diagnostics(string $documentText): array
    {
        $known = array_map(fn (ConfigKeyEntry $e) => $e->key, $this->index->getConfigKeys());
        $diagnostics = [];
        $lines = explode("\n", $documentText);

        foreach ($lines as $lineNum => $line) {
            $pattern = '/->\s*(' . implode('|', self::CONFIG_GETTER_METHODS) . ')\s*\(\s*[\'"]([^\'"]+)[\'"]/';

            if (preg_match_all($pattern, $line, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[2] as [$key, $offset]) {
                    if (in_array($key, $known, true)) {
                        continue;
                    }

                    if (preg_match('/^scopes\.[^.]+\.(.+)$/', $key, $cm)) {
                        $defaultEquivalent = 'default.' . $cm[1];

                        if (in_array($defaultEquivalent, $known, true)) {
                            continue;
                        }
                    }

                    $diagnostics[] = [
                        'range' => [
                            'start' => ['line' => $lineNum, 'character' => $offset],
                            'end' => ['line' => $lineNum, 'character' => $offset + strlen($key)],
                        ],
                        'severity' => 1,
                        'message' => "Unknown config key: $key",
                        'code' => 'marko.config.unknown_key',
                    ];
                }
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<string>
     */
    public function suggestSimilar(string $key, int $max = 3): array
    {
        $candidates = array_map(fn (ConfigKeyEntry $e) => $e->key, $this->index->getConfigKeys());
        $scored = array_map(fn (string $c) => ['key' => $c, 'distance' => levenshtein($key, $c)], $candidates);
        usort($scored, fn (array $a, array $b) => $a['distance'] <=> $b['distance']);

        return array_map(fn (array $s) => $s['key'], array_slice($scored, 0, $max));
    }
}
