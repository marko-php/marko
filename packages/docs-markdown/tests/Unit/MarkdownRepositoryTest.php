<?php

declare(strict_types=1);

use Marko\DocsMarkdown\Exceptions\DocsMarkdownException;
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

it('reads markdown for a legitimate page id', function (): void {
    $docsPath = dirname(__DIR__, 2) . '/docs';
    $repo = new MarkdownRepository($docsPath);

    $content = $repo->getRawMarkdown('getting-started/installation');

    expect($content)->toBeString()
        ->and(strlen($content))->toBeGreaterThan(0);
});

it('throws DocsMarkdownException for an id containing path-traversal segments', function (): void {
    $docsPath = dirname(__DIR__, 2) . '/docs';
    $repo = new MarkdownRepository($docsPath);

    // Create a real .md file outside the docs root so traversal resolves to a real path
    $outsidePath = sys_get_temp_dir() . '/marko-traversal-test-' . uniqid() . '.md';
    file_put_contents($outsidePath, '# Secret content');

    $relativeId = str_repeat('../', 20) . ltrim($outsidePath, '/');
    $relativeId = substr($relativeId, 0, -3); // strip .md suffix

    try {
        expect(fn () => $repo->getRawMarkdown($relativeId))
            ->toThrow(DocsMarkdownException::class);
    } finally {
        @unlink($outsidePath);
    }
});

it('does not read a .md file outside the docs directory via a traversal id', function (): void {
    $docsPath = dirname(__DIR__, 2) . '/docs';
    $repo = new MarkdownRepository($docsPath);

    // Create a real .md file outside the docs root to confirm traversal is blocked
    $outsidePath = sys_get_temp_dir() . '/marko-traversal-secret-' . uniqid() . '.md';
    file_put_contents($outsidePath, '# Secret content');

    $relativeId = str_repeat('../', 20) . ltrim($outsidePath, '/');
    $relativeId = substr($relativeId, 0, -3); // strip .md suffix

    $caughtException = null;

    try {
        $repo->getRawMarkdown($relativeId);
    } catch (DocsMarkdownException $e) {
        $caughtException = $e;
    } finally {
        @unlink($outsidePath);
    }

    expect($caughtException)->not->toBeNull()
        ->and($caughtException->getMessage())->not->toContain('Secret content');
});

it('still throws pageNotFound for a clean id that does not exist', function (): void {
    $docsPath = dirname(__DIR__, 2) . '/docs';
    $repo = new MarkdownRepository($docsPath);

    expect(fn () => $repo->getRawMarkdown('nonexistent-page'))
        ->toThrow(DocsMarkdownException::class);
});

it('populates message, context, and suggestion on the traversal exception', function (): void {
    $docsPath = dirname(__DIR__, 2) . '/docs';
    $repo = new MarkdownRepository($docsPath);

    // Create a real .md file outside the docs root so traversal resolves to a real path
    $outsidePath = sys_get_temp_dir() . '/marko-traversal-fields-' . uniqid() . '.md';
    file_put_contents($outsidePath, '# Secret content');

    $relativeId = str_repeat('../', 20) . ltrim($outsidePath, '/');
    $relativeId = substr($relativeId, 0, -3); // strip .md suffix

    $exception = null;

    try {
        $repo->getRawMarkdown($relativeId);
    } catch (DocsMarkdownException $e) {
        $exception = $e;
    } finally {
        @unlink($outsidePath);
    }

    expect($exception)->not->toBeNull()
        ->and($exception->getMessage())->toContain($relativeId)
        ->and($exception->getContext())->toContain($docsPath)
        ->and($exception->getSuggestion())->toBeString()
        ->and(strlen($exception->getSuggestion()))->toBeGreaterThan(0);
});
