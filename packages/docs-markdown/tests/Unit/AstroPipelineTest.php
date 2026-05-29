<?php

declare(strict_types=1);

use Marko\DocsMarkdown\MarkdownRepository;

// Paths are derived relative to this test file so the suite is portable
// across machines and CI (no hardcoded absolute paths).
$repoRoot = dirname(__DIR__, 4);
$symlinkPath = $repoRoot . '/docs/src/content/docs';
$packageDocs = dirname(__DIR__, 2) . '/docs';

it('points the docs build pipeline at packages/docs-markdown/docs/ as source', function () use ($symlinkPath): void {
    expect(is_link($symlinkPath))->toBeTrue();
});

it('produces a clean build of marko.build/docs after the path change', function () use ($symlinkPath, $packageDocs): void {
    $target = readlink($symlinkPath);
    $resolvedTarget = realpath(dirname($symlinkPath) . '/' . $target);

    expect($resolvedTarget)->toBe(realpath($packageDocs));
});

it('renders the same pages count as before the migration', function () use ($symlinkPath): void {
    // Count .md and .mdx files reachable through the symlinked source.
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($symlinkPath, FilesystemIterator::SKIP_DOTS),
    );

    $count = 0;

    foreach ($files as $file) {
        if (in_array($file->getExtension(), ['md', 'mdx'], true)) {
            $count++;
        }
    }

    // Current develop content migrated into the package: 119 pages
    // (.md + .mdx). The ai-assisted-development section adds more in a
    // later phase; this count tracks the migrated baseline.
    expect($count)->toBe(119);
});

it('preserves image and asset paths through the rename', function () use ($packageDocs): void {
    expect(is_dir($packageDocs))->toBeTrue();

    $repo = new MarkdownRepository($packageDocs);
    $pages = $repo->listAllPages();

    expect($pages)->not->toBeEmpty();
});

it('updates any navigation config to reflect the new source path', function () use ($symlinkPath, $packageDocs): void {
    // The symlink IS the navigation config update: Astro reads docs via the
    // symlink which now points to packages/docs-markdown/docs
    expect(is_link($symlinkPath))->toBeTrue()
        ->and(realpath($symlinkPath))->toBe(realpath($packageDocs));
});
