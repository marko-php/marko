<?php

declare(strict_types=1);

use Marko\DocsMarkdown\MarkdownRepository;

it('has MarkdownRepository with listAllPages and getRawMarkdown id methods', function (): void {
    expect(method_exists(MarkdownRepository::class, 'listAllPages'))->toBeTrue()
        ->and(method_exists(MarkdownRepository::class, 'getRawMarkdown'))->toBeTrue();
});

it('exposes absolute path to docs root via a dedicated accessor', function (): void {
    $docsPath = dirname(__DIR__, 2) . '/docs';
    $repo = new MarkdownRepository($docsPath);

    expect($repo->getDocsPath())->toBe($docsPath);
});

it('returns file content matching the docs files reachable via the Astro symlink', function (): void {
    $docsPath = dirname(__DIR__, 2) . '/docs';
    $repo = new MarkdownRepository($docsPath);

    $content = $repo->getRawMarkdown('getting-started/installation');

    // The Astro site reads the same files through the symlink at
    // docs/src/content/docs → packages/docs-markdown/docs. Derive the path
    // relative to the repo root (portable; no hardcoded absolute path).
    $symlinkedPath = dirname(__DIR__, 4) . '/docs/src/content/docs/getting-started/installation.md';
    expect($content)->toBe((string) file_get_contents($symlinkedPath));
});
