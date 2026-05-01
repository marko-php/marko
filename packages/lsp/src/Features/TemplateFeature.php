<?php

declare(strict_types=1);

namespace Marko\Lsp\Features;

use Marko\CodeIndexer\Cache\IndexCache;

readonly class TemplateFeature
{
    public function __construct(private IndexCache $index) {}

    /** @return list<array{label: string, kind: int, detail: string, insertText: string}> */
    public function complete(string $partial): array
    {
        $items = [];

        foreach ($this->index->getTemplates() as $t) {
            $fullName = "$t->moduleName::$t->templateName";

            if ($partial !== '' && !str_starts_with($fullName, $partial) && !str_starts_with(
                $t->templateName,
                $partial
            )) {
                continue;
            }

            $items[] = [
                'label' => $fullName,
                'kind' => 17,
                'detail' => $t->absolutePath,
                'insertText' => $fullName,
            ];
        }

        return $items;
    }

    /** @return ?array{uri: string, range: array} */
    public function gotoDefinition(string $template): ?array
    {
        $parts = explode('::', $template, 2);

        if (count($parts) === 2) {
            [$module, $name] = $parts;

            foreach ($this->index->getTemplates() as $t) {
                if ($t->moduleName === $module && $t->templateName === $name) {
                    return [
                        'uri' => 'file://' . $t->absolutePath,
                        'range' => [
                            'start' => ['line' => 0, 'character' => 0],
                            'end' => ['line' => 0, 'character' => 0],
                        ],
                    ];
                }
            }
        } else {
            // plain name without module prefix — first match wins
            foreach ($this->index->getTemplates() as $t) {
                if ($t->templateName === $template) {
                    return [
                        'uri' => 'file://' . $t->absolutePath,
                        'range' => [
                            'start' => ['line' => 0, 'character' => 0],
                            'end' => ['line' => 0, 'character' => 0],
                        ],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Markdown hover content for a template, or null if unknown.
     */
    public function hover(string $template): ?string
    {
        $parts = explode('::', $template, 2);

        foreach ($this->index->getTemplates() as $t) {
            $matches = count($parts) === 2
                ? ($t->moduleName === $parts[0] && $t->templateName === $parts[1])
                : ($t->templateName === $template);

            if ($matches) {
                return "**$t->moduleName::$t->templateName** _{$t->extension}_\n\nFile: `$t->absolutePath`";
            }
        }

        return null;
    }

    /** @return list<array{range: array, severity: int, message: string, code: string}> */
    public function diagnostics(string $documentText): array
    {
        $known = [];

        foreach ($this->index->getTemplates() as $t) {
            $known[] = "$t->moduleName::$t->templateName";
            $known[] = $t->templateName;
        }

        $diagnostics = [];
        $lines = explode("\n", $documentText);

        foreach ($lines as $lineNum => $line) {
            $pattern = '/->render\s*\(\s*[\'"]([^\'"]+)[\'"]/';

            if (preg_match_all($pattern, $line, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[1] as [$tpl, $offset]) {
                    if (in_array($tpl, $known, true)) {
                        continue;
                    }

                    $diagnostics[] = [
                        'range' => [
                            'start' => ['line' => $lineNum, 'character' => $offset],
                            'end' => ['line' => $lineNum, 'character' => $offset + strlen($tpl)],
                        ],
                        'severity' => 1,
                        'message' => "Template not found: $tpl",
                        'code' => 'marko.template.not_found',
                    ];
                }
            }
        }

        return $diagnostics;
    }

    /** @return list<string> */
    public function suggestSimilar(
        string $template,
        int $max = 3,
    ): array
    {
        $candidates = [];

        foreach ($this->index->getTemplates() as $t) {
            $candidates[] = "$t->moduleName::$t->templateName";
        }

        $scored = array_map(fn (string $c) => ['name' => $c, 'distance' => levenshtein($template, $c)], $candidates);
        usort($scored, fn (array $a, array $b) => $a['distance'] <=> $b['distance']);

        return array_map(fn (array $s) => $s['name'], array_slice($scored, 0, $max));
    }
}
