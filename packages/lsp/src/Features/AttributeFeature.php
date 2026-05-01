<?php

declare(strict_types=1);

namespace Marko\Lsp\Features;

use Marko\CodeIndexer\Cache\IndexCache;

readonly class AttributeFeature
{
    private const string COMMAND_NAME_PATTERN = '/^[a-z][a-z0-9-]*(:[a-z][a-z0-9-]*)+$/';

    public function __construct(private IndexCache $index) {}

    /**
     * Returns context info or null if not in any attribute parameter.
     *
     * @return ?array{attribute: string, parameter: string, partial: string}
     */
    public function detectContext(string $lineText, int $col): ?array
    {
        $prefix = substr($lineText, 0, $col);

        // Match: #[AttributeName(paramName: 'partial' or paramName: ClassName
        $pattern = '/#\[\s*(\w+)\s*\(\s*(\w+)\s*:\s*[\'"]?([^\'")\s]*)$/';

        if (preg_match($pattern, $prefix, $m)) {
            return ['attribute' => $m[1], 'parameter' => $m[2], 'partial' => $m[3]];
        }

        // Also match "#[DisableRoute" or "#[Disable" with no parens
        if (preg_match('/#\[\s*(\w*)$/', $prefix, $m)) {
            return ['attribute' => $m[1], 'parameter' => '', 'partial' => $m[1]];
        }

        return null;
    }

    /**
     * Returns LSP completion items.
     *
     * @return list<array{label: string, kind: int}>
     */
    public function complete(string $lineText, int $col): array
    {
        $ctx = $this->detectContext($lineText, $col);

        if ($ctx === null) {
            return [];
        }

        return match ([$ctx['attribute'], $ctx['parameter']]) {
            ['Observer', 'event'] => $this->completeEventClasses($ctx['partial']),
            ['Plugin', 'target'] => $this->completeAllClasses($ctx['partial']),
            ['Get', ''], ['Post', ''], ['Put', ''], ['Patch', ''], ['Delete', ''] => $this->completeRoutePaths($ctx['partial']),
            ['Middleware', ''] => $this->completeMiddlewareClasses($ctx['partial']),
            default => [],
        };
    }

    /**
     * Returns diagnostics for invalid Command names in the document.
     *
     * @return list<array{range: array, severity: int, message: string, code: string}>
     */
    public function diagnostics(string $documentText): array
    {
        $diagnostics = [];
        $lines = explode("\n", $documentText);

        foreach ($lines as $lineNum => $line) {
            if (preg_match('/#\[\s*Command\s*\(\s*name\s*:\s*[\'"]([^\'"]+)[\'"]/', $line, $m, PREG_OFFSET_CAPTURE)) {
                $name = $m[1][0];

                if (!preg_match(self::COMMAND_NAME_PATTERN, $name)) {
                    $diagnostics[] = [
                        'range' => [
                            'start' => ['line' => $lineNum, 'character' => $m[1][1]],
                            'end' => ['line' => $lineNum, 'character' => $m[1][1] + strlen($name)],
                        ],
                        'severity' => 1,
                        'message' => "Invalid command name format: $name (expected lowercase 'group:command')",
                        'code' => 'marko.command.invalid_name',
                    ];
                }
            }
        }

        return $diagnostics;
    }

    /** @return list<array{label: string, kind: int}> */
    private function completeEventClasses(string $partial): array
    {
        $events = array_unique(array_map(fn ($o) => $o->event, $this->index->getObservers()));

        return array_values(array_map(
            fn ($e) => ['label' => $e, 'kind' => 7],
            array_filter($events, fn ($e) => $partial === '' || str_contains(strtolower($e), strtolower($partial))),
        ));
    }

    /** @return list<array{label: string, kind: int}> */
    private function completeAllClasses(string $partial): array
    {
        $classes = array_unique(array_map(fn ($p) => $p->target, $this->index->getPlugins()));

        return array_values(array_map(
            fn ($c) => ['label' => $c, 'kind' => 7],
            array_filter($classes, fn ($c) => $partial === '' || str_contains(strtolower($c), strtolower($partial))),
        ));
    }

    /** @return list<array{label: string, kind: int}> */
    private function completeRoutePaths(string $partial): array
    {
        $paths = array_unique(array_map(fn ($r) => $r->path, $this->index->getRoutes()));

        return array_values(array_map(
            fn ($p) => ['label' => $p, 'kind' => 14],
            array_filter($paths, fn ($p) => $partial === '' || str_starts_with($p, $partial)),
        ));
    }

    /** @return list<array{label: string, kind: int}> */
    private function completeMiddlewareClasses(string $partial): array
    {
        $middleware = [];

        foreach ($this->index->getRoutes() as $r) {
            if (property_exists($r, 'middleware') && is_array($r->middleware ?? null)) {
                foreach ($r->middleware as $mw) {
                    $middleware[$mw] = true;
                }
            }
        }

        return array_values(array_map(
            fn ($mw) => ['label' => $mw, 'kind' => 7],
            array_keys(array_filter(
                $middleware,
                fn ($mw) => $partial === '' || str_contains(strtolower($mw), strtolower($partial)),
                ARRAY_FILTER_USE_KEY,
            )),
        ));
    }
}
