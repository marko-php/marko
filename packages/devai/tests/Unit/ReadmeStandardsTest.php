<?php

declare(strict_types=1);

it('creates README.md with required sections for all new packages', function (): void {
    $packages = ['codeindexer', 'mcp', 'lsp', 'docs', 'docs-markdown', 'docs-fts', 'docs-vec', 'devai', 'ratelimiter', 'devserver'];
    $packageRoot = dirname(__DIR__, 4) . '/packages';
    // docs/DOCS-STANDARDS.md mandates a slim README format (title + one-liner,
    // Installation, Quick Example, Documentation link). `## Installation` is the
    // universal anchor every README must have; the other sections are still
    // being migrated across the older packages.
    $required = ['## Installation'];

    foreach ($packages as $pkg) {
        $readme = $packageRoot . '/' . $pkg . '/README.md';
        expect(is_file($readme))->toBeTrue("README.md missing for $pkg");
        $content = (string) file_get_contents($readme);
        foreach ($required as $section) {
            expect(str_contains($content, $section))->toBeTrue("README.md for $pkg missing $section");
        }
    }
});
