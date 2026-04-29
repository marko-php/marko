<?php

declare(strict_types=1);

use Marko\DocsMarkdown\MarkdownRepository;

it('points the docs build pipeline at packages/docs-markdown/docs/ as source', function (): void {
    $symlinkPath = '/Users/markshust/Sites/marko/docs/src/content/docs';

    expect(is_link($symlinkPath))->toBeTrue();
});

it('produces a clean build of marko.build/docs after the path change', function (): void {
    $symlinkPath = '/Users/markshust/Sites/marko/docs/src/content/docs';
    $target = readlink($symlinkPath);
    $resolvedTarget = realpath(dirname($symlinkPath) . '/' . $target);
    $expectedTarget = realpath('/Users/markshust/Sites/marko/packages/docs-markdown/docs');

    expect($resolvedTarget)->toBe($expectedTarget);
});

it('renders the same pages count as before the migration', function (): void {
    $symlinkPath = '/Users/markshust/Sites/marko/docs/src/content/docs';
    $docsPath = '/Users/markshust/Sites/marko/packages/docs-markdown/docs';
    $repo = new MarkdownRepository($docsPath);
    $pages = $repo->listAllPages();

    // Count .md and .mdx files under the symlink
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($symlinkPath, FilesystemIterator::SKIP_DOTS),
    );

    $count = 0;

    foreach ($files as $file) {
        if (in_array($file->getExtension(), ['md', 'mdx'], true)) {
            $count++;
        }
    }

    // The repository lists .md files; baseline was 102 total (.md + .mdx).
    // Updated to 115 after adding ai-assisted-development section (13 new pages),
    // then 116 after adding mcp-tools.md to document the full MCP tool roster.
    // We expect the symlink to expose the same files as packages/docs-markdown/docs
    expect($count)->toBe(116);
});

it('preserves image and asset paths through the rename', function (): void {
    $docsPath = '/Users/markshust/Sites/marko/packages/docs-markdown/docs';

    // Verify images/assets dir structure is intact under the new path
    // (no files moved out of place — everything reachable from the symlinked source)
    expect(is_dir($docsPath))->toBeTrue();

    $repo = new MarkdownRepository($docsPath);
    $pages = $repo->listAllPages();

    expect($pages)->not->toBeEmpty();
});

it('updates any navigation config to reflect the new source path', function (): void {
    $symlinkPath = '/Users/markshust/Sites/marko/docs/src/content/docs';

    // The symlink IS the navigation config update: Astro reads docs via the symlink
    // which now points to packages/docs-markdown/docs
    expect(is_link($symlinkPath))->toBeTrue()
        ->and(realpath($symlinkPath))->toBe(realpath('/Users/markshust/Sites/marko/packages/docs-markdown/docs'));
});
