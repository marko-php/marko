<?php

declare(strict_types=1);

use Marko\Docs\Contract\DocsSearchInterface;
use Marko\Docs\Exceptions\DocsException;
use Marko\Docs\ValueObject\DocsNavEntry;
use Marko\Docs\ValueObject\DocsPage;
use Marko\Docs\ValueObject\DocsQuery;
use Marko\Docs\ValueObject\DocsResult;
use Marko\DocsFts\FtsSearch;
use Marko\DocsFts\Indexing\FtsIndexBuilder;
use Marko\DocsMarkdown\MarkdownRepository;

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir() . '/docs-fts-test-' . uniqid();
    mkdir($this->tempDir, 0755, true);

    $docsPath = $this->tempDir . '/docs';
    mkdir($docsPath . '/getting-started', 0755, true);
    file_put_contents($docsPath . '/getting-started/installation.md', "# Installation\n\nHow to install Marko Framework...");
    file_put_contents($docsPath . '/getting-started/quickstart.md', "# Quickstart\n\nFirst steps with the framework after installation.");
    file_put_contents($docsPath . '/index.md', "# Welcome\n\nMarko documentation home.");

    $repo = new MarkdownRepository($docsPath);
    $indexPath = $this->tempDir . '/docs.sqlite';

    $builder = new FtsIndexBuilder($repo);
    $builder->build($indexPath);

    $this->search = new FtsSearch($repo, $indexPath);
});

afterEach(function (): void {
    $dir = $this->tempDir;
    if (is_dir($dir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }
});

it('implements DocsSearchInterface', function (): void {
    expect($this->search)->toBeInstanceOf(DocsSearchInterface::class);
});

it('returns ranked DocsResult list for a valid query', function (): void {
    $results = $this->search->search(new DocsQuery('installation'));

    expect($results)->not->toBeEmpty()
        ->and($results[0])->toBeInstanceOf(DocsResult::class);
});

it('respects the query limit parameter', function (): void {
    $results = $this->search->search(new DocsQuery('installation', limit: 1));

    expect($results)->toHaveCount(1);
});

it('generates excerpt snippets highlighting query terms', function (): void {
    $results = $this->search->search(new DocsQuery('installation'));

    expect($results)->not->toBeEmpty()
        ->and($results[0]->excerpt)->toContain('<mark>');
});

it('returns empty list for a query with no matches', function (): void {
    $results = $this->search->search(new DocsQuery('xyzzyqqqqq'));

    expect($results)->toBeEmpty();
});

it('throws DocsException with context when SQLite file is missing', function (): void {
    $repo = new MarkdownRepository($this->tempDir . '/docs');
    $search = new FtsSearch($repo, '/nonexistent/path/docs.sqlite');

    expect(fn () => $search->search(new DocsQuery('test')))->toThrow(DocsException::class);
});

it('returns DocsPage via getPage for a valid page id', function (): void {
    $page = $this->search->getPage('getting-started/installation');

    expect($page)->toBeInstanceOf(DocsPage::class)
        ->and($page->id)->toBe('getting-started/installation')
        ->and($page->content)->toContain('install');
});

it('returns nav tree via listNav', function (): void {
    $nav = $this->search->listNav();

    expect($nav)->not->toBeEmpty()
        ->and($nav[0])->toBeInstanceOf(DocsNavEntry::class);
});
