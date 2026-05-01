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

it('returns file content matching original monorepo docs files', function (): void {
    $docsPath = dirname(__DIR__, 2) . '/docs';
    $repo = new MarkdownRepository($docsPath);

    $content = $repo->getRawMarkdown('getting-started/installation');

    $originalPath = '/Users/markshust/Sites/marko/docs/src/content/docs/getting-started/installation.md';
    expect($content)->toBe((string) file_get_contents($originalPath));
});
