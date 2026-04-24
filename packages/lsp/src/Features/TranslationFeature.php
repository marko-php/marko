<?php

declare(strict_types=1);

namespace Marko\Lsp\Features;

use Marko\CodeIndexer\Cache\IndexCache;

readonly class TranslationFeature
{
    private const array TRANSLATOR_METHODS = ['get', 'choice', 'has'];

    public function __construct(private IndexCache $index, private string $defaultLocale = 'en') {}

    /**
     * Detects whether the cursor is inside a translator method string literal.
     * Returns the partial key typed so far, or null if not in such context.
     */
    public function detectContext(string $lineText, int $col): ?string
    {
        $prefix = substr($lineText, 0, $col);
        $pattern = '/->\s*(' . implode('|', self::TRANSLATOR_METHODS) . ')\s*\(\s*[\'"]([^\'"]*)$/';

        if (preg_match($pattern, $prefix, $m)) {
            return $m[2];
        }

        return null;
    }

    /**
     * Returns LSP completion items filtered to the default locale to avoid duplicates.
     *
     * @return list<array{label: string, kind: int, detail: string, documentation: string, insertText: string}>
     */
    public function complete(string $partial): array
    {
        $items = [];

        foreach ($this->index->getTranslationKeys() as $entry) {
            if ($entry->locale !== $this->defaultLocale) {
                continue;
            }

            $fullKey = $entry->namespace !== null
                ? $entry->namespace . '::' . $entry->group . '.' . $entry->key
                : $entry->group . '.' . $entry->key;

            if ($partial !== '' && !str_starts_with($fullKey, $partial)) {
                continue;
            }

            $items[] = [
                'label' => $fullKey,
                'kind' => 14,
                'detail' => "[$entry->locale] $entry->group",
                'documentation' => "From module: $entry->module\nFile: $entry->file:$entry->line",
                'insertText' => $fullKey,
            ];
        }

        return $items;
    }

    /**
     * @return ?array{uri: string, range: array}
     */
    public function gotoDefinition(string $key): ?array
    {
        foreach ($this->index->getTranslationKeys() as $entry) {
            $fullKey = $entry->namespace !== null
                ? $entry->namespace . '::' . $entry->group . '.' . $entry->key
                : $entry->group . '.' . $entry->key;

            if ($fullKey === $key) {
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
     * Returns diagnostics for translation keys missing from the default locale.
     *
     * @return list<array{range: array, severity: int, message: string, code: string}>
     */
    public function diagnostics(string $documentText): array
    {
        $known = [];

        foreach ($this->index->getTranslationKeys() as $entry) {
            if ($entry->locale !== $this->defaultLocale) {
                continue;
            }

            $fullKey = $entry->namespace !== null
                ? $entry->namespace . '::' . $entry->group . '.' . $entry->key
                : $entry->group . '.' . $entry->key;

            $known[] = $fullKey;
        }

        $diagnostics = [];
        $lines = explode("\n", $documentText);

        foreach ($lines as $lineNum => $line) {
            $pattern = '/->\s*(' . implode('|', self::TRANSLATOR_METHODS) . ')\s*\(\s*[\'"]([^\'"]+)[\'"]/';

            if (preg_match_all($pattern, $line, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[2] as [$key, $offset]) {
                    if (in_array($key, $known, true)) {
                        continue;
                    }

                    $diagnostics[] = [
                        'range' => [
                            'start' => ['line' => $lineNum, 'character' => $offset],
                            'end' => ['line' => $lineNum, 'character' => $offset + strlen($key)],
                        ],
                        'severity' => 1,
                        'message' => "Unknown translation key: $key",
                        'code' => 'marko.translation.unknown_key',
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
        $candidates = [];

        foreach ($this->index->getTranslationKeys() as $entry) {
            if ($entry->locale !== $this->defaultLocale) {
                continue;
            }

            $candidates[] = $entry->namespace !== null
                ? $entry->namespace . '::' . $entry->group . '.' . $entry->key
                : $entry->group . '.' . $entry->key;
        }

        $scored = array_map(fn (string $c) => ['key' => $c, 'distance' => levenshtein($key, $c)], $candidates);
        usort($scored, fn (array $a, array $b) => $a['distance'] <=> $b['distance']);

        return array_map(fn (array $s) => $s['key'], array_slice($scored, 0, $max));
    }
}
