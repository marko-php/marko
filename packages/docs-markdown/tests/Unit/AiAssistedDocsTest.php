<?php

declare(strict_types=1);

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
});
