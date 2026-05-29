<?php

declare(strict_types=1);

// Skipped until the marko/devai phase. The ai-assisted-development section
// documents mcp, lsp, devai, and the per-agent integrations — none of which
// exist on develop yet. The section content is added to this package and this
// test is un-skipped together with marko/devai in a later split PR.
it('adds an AI-assisted development section with required pages', function () {
    $docsRoot = dirname(__DIR__, 2) . '/docs/ai-assisted-development';
    $required = [
        'index.md', 'installation.md', 'docs-drivers.md', 'contributing.md',
        'troubleshooting.md', 'verification-checklist.md', 'architecture.md',
        'agents/claude-code.md', 'agents/codex.md', 'agents/cursor.md',
        'agents/copilot.md', 'agents/gemini-cli.md', 'agents/junie.md',
    ];

    foreach ($required as $rel) {
        $path = $docsRoot . '/' . $rel;
        expect(is_file($path))->toBeTrue("Missing: $rel");
        $content = (string) file_get_contents($path);
        expect(str_starts_with($content, '---'))->toBeTrue("Missing frontmatter in $rel");
        expect(str_contains($content, 'title:'))->toBeTrue("Missing title frontmatter in $rel");
    }
})->skip('ai-assisted-development section lands with marko/devai (later split PR)');
