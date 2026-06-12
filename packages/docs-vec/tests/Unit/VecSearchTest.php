<?php

declare(strict_types=1);

use Marko\Docs\Contract\DocsSearchInterface;
use Marko\Docs\Exceptions\DocsException;
use Marko\Docs\ValueObject\DocsQuery;
use Marko\Docs\ValueObject\DocsResult;
use Marko\DocsMarkdown\MarkdownRepository;
use Marko\DocsVec\Indexing\HybridIndexBuilder;
use Marko\DocsVec\Query\QueryEmbedder;
use Marko\DocsVec\Runtime\VecRuntime;
use Marko\DocsVec\VecSearch;

it('implements DocsSearchInterface', function (): void {
    expect(VecSearch::class)->toImplement(DocsSearchInterface::class);
});

it('throws DocsException with context when SQLite or model is missing', function (): void {
    $repo = new MarkdownRepository(sys_get_temp_dir());
    $runtime = new VecRuntime(dirname(__DIR__, 2));
    $embedder = new QueryEmbedder($runtime);
    $search = new VecSearch(
        repository: $repo,
        runtime: $runtime,
        embedder: $embedder,
        indexPath: '/nonexistent/path/docs.sqlite',
    );

    expect(fn () => $search->search(new DocsQuery('test')))->toThrow(DocsException::class);
});

it('runs an FTS5 MATCH query and a vector similarity query in parallel', function (): void {
    $runtime = new VecRuntime(dirname(__DIR__, 2));

    if (! $runtime->isSqliteVecAvailable()) {
        $this->markTestSkipped('sqlite-vec extension not available');
    }

    [$search] = buildTestIndex($runtime);

    $results = $search->search(new DocsQuery('installation'));

    expect($results)->not->toBeEmpty();
})->skip(fn () => ! (new VecRuntime(dirname(__DIR__, 2)))->isSqliteVecAvailable(), 'sqlite-vec not available');

it('merges ranks via Reciprocal Rank Fusion', function (): void {
    $runtime = new VecRuntime(dirname(__DIR__, 2));

    if (! $runtime->isSqliteVecAvailable()) {
        $this->markTestSkipped('sqlite-vec extension not available');
    }

    [$search] = buildTestIndex($runtime);

    $results = $search->search(new DocsQuery('installation'));

    expect($results)->not->toBeEmpty()
        ->and($results[0])->toBeInstanceOf(DocsResult::class)
        ->and($results[0]->score)->toBeGreaterThan(0.0);
})->skip(fn () => ! (new VecRuntime(dirname(__DIR__, 2)))->isSqliteVecAvailable(), 'sqlite-vec not available');

it('returns top-N DocsResult objects by combined RRF score', function (): void {
    $runtime = new VecRuntime(dirname(__DIR__, 2));

    if (! $runtime->isSqliteVecAvailable()) {
        $this->markTestSkipped('sqlite-vec extension not available');
    }

    [$search] = buildTestIndex($runtime);

    $results = $search->search(new DocsQuery('installation', limit: 1));

    expect($results)->toHaveCount(1)
        ->and($results[0])->toBeInstanceOf(DocsResult::class);
})->skip(fn () => ! (new VecRuntime(dirname(__DIR__, 2)))->isSqliteVecAvailable(), 'sqlite-vec not available');

it('generates excerpt snippets from FTS5 highlighting', function (): void {
    $runtime = new VecRuntime(dirname(__DIR__, 2));

    if (! $runtime->isSqliteVecAvailable()) {
        $this->markTestSkipped('sqlite-vec extension not available');
    }

    [$search] = buildTestIndex($runtime);

    $results = $search->search(new DocsQuery('installation'));

    expect($results)->not->toBeEmpty()
        ->and($results[0]->excerpt)->toContain('<mark>');
})->skip(fn () => ! (new VecRuntime(dirname(__DIR__, 2)))->isSqliteVecAvailable(), 'sqlite-vec not available');

it('returns empty list for a query with no matches', function (): void {
    $runtime = new VecRuntime(dirname(__DIR__, 2));

    if (! $runtime->isSqliteVecAvailable()) {
        $this->markTestSkipped('sqlite-vec extension not available');
    }

    [$search] = buildTestIndex($runtime);

    $results = $search->search(new DocsQuery('xyzzyqqqqq12345'));

    expect($results)->toBeEmpty();
})->skip(fn () => ! (new VecRuntime(dirname(__DIR__, 2)))->isSqliteVecAvailable(), 'sqlite-vec not available');

it(
    'returns ranked DocsResult objects for a query that produces one or more FTS hits without erroring',
    function (): void {
        $runtime = new VecRuntime(dirname(__DIR__, 2));
        [$search] = buildFtsTestIndex($runtime);

        $results = $search->search(new DocsQuery('installation'));

        expect($results)->not->toBeEmpty()
            ->and($results[0])->toBeInstanceOf(DocsResult::class)
            ->and($results[0]->score)->toBeGreaterThan(0.0);
    },
);

it(
    'combines FTS and vector RRF scores into a single bucket and sets the chunk for an FTS-only entry',
    function (): void {
        $runtime = new VecRuntime(dirname(__DIR__, 2));
        [$search] = buildFtsTestIndex($runtime);

        $results = $search->search(new DocsQuery('installation'));

        expect($results)->not->toBeEmpty();

        foreach ($results as $result) {
            expect($result)->toBeInstanceOf(DocsResult::class)
                ->and($result->pageId)->not->toBeEmpty()
                ->and($result->score)->toBeGreaterThan(0.0);
        }
    },
);

it('still searches successfully on the FTS-only path when no embedding model is available', function (): void {
    // Use a VecRuntime pointing at a non-existent model dir so isModelAvailable() returns false
    $runtime = new VecRuntime('/nonexistent/model/root');
    [$search] = buildFtsTestIndex($runtime);

    $results = $search->search(new DocsQuery('installation'));

    expect($results)->not->toBeEmpty()
        ->and($results[0])->toBeInstanceOf(DocsResult::class);
});

it(
    'preserves the existing DocsException when the index file is missing (does not convert it twice)',
    function (): void {
        $runtime = new VecRuntime(dirname(__DIR__, 2));
        $repo = new MarkdownRepository(sys_get_temp_dir());
        $embedder = new QueryEmbedder($runtime);
        $search = new VecSearch(
            repository: $repo,
            runtime: $runtime,
            embedder: $embedder,
            indexPath: '/nonexistent/path/docs.sqlite',
        );

        try {
            $search->search(new DocsQuery('test'));
            $this->fail('Expected DocsException to be thrown');
        } catch (DocsException $e) {
            expect($e->getMessage())->toContain('Index not found');
        }
    },
);

it('returns an empty list for a valid query that matches no documents', function (): void {
    $runtime = new VecRuntime(dirname(__DIR__, 2));
    [$search] = buildFtsTestIndex($runtime);

    $results = $search->search(new DocsQuery('xyzzyqqqqq12345nomatches'));

    expect($results)->toBeEmpty();
});

it(
    'throws DocsException not PDOException when the search text is a malformed FTS5 MATCH expression',
    function (): void {
        $runtime = new VecRuntime(dirname(__DIR__, 2));
        [$search] = buildFtsTestIndex($runtime);

        // NEAR/ is a malformed FTS5 MATCH expression (syntax error)
        expect(fn () => $search->search(new DocsQuery('NEAR/')))->toThrow(DocsException::class);
    },
);

it('includes the underlying FTS5 parse reason in the DocsException message', function (): void {
    $runtime = new VecRuntime(dirname(__DIR__, 2));
    [$search] = buildFtsTestIndex($runtime);

    try {
        $search->search(new DocsQuery('NEAR/'));
        $this->fail('Expected DocsException to be thrown');
    } catch (DocsException $e) {
        expect($e->getMessage())->toContain('fts5');
    }
});

// Helper: builds a real index in a temp dir using FTS-only (no model needed for basic search tests)
// Returns [VecSearch, tempDir]
function buildTestIndex(VecRuntime $runtime): array
{
    $tempDir = sys_get_temp_dir() . '/docs-vec-test-' . uniqid();
    mkdir($tempDir, 0755, true);

    $docsPath = $tempDir . '/docs';
    mkdir($docsPath . '/getting-started', 0755, true);
    file_put_contents(
        $docsPath . '/getting-started/installation.md',
        "# Installation\n\nHow to install Marko Framework step by step.",
    );
    file_put_contents(
        $docsPath . '/getting-started/quickstart.md',
        "# Quickstart\n\nFirst steps with the framework after installation.",
    );
    file_put_contents($docsPath . '/index.md', "# Welcome\n\nMarko documentation home.");

    $indexPath = $tempDir . '/docs.sqlite';
    $repo = new MarkdownRepository($docsPath);

    // Build index using HybridIndexBuilder only if model available; otherwise build FTS-only schema
    if ($runtime->isModelAvailable()) {
        $builder = new HybridIndexBuilder($repo, $runtime);
        $builder->build($indexPath);
    } else {
        buildFtsOnlyIndex($runtime, $repo, $indexPath);
    }

    $embedder = new QueryEmbedder($runtime);
    $search = new VecSearch(
        repository: $repo,
        runtime: $runtime,
        embedder: $embedder,
        indexPath: $indexPath,
    );

    return [$search, $tempDir];
}

// Builds a VecSearch backed by an FTS-only index, no model or sqlite-vec needed.
// Returns [VecSearch, tempDir]
function buildFtsTestIndex(VecRuntime $runtime): array
{
    $tempDir = sys_get_temp_dir() . '/docs-vec-fts-test-' . uniqid();
    mkdir($tempDir, 0755, true);

    $docsPath = $tempDir . '/docs';
    mkdir($docsPath . '/getting-started', 0755, true);
    file_put_contents(
        $docsPath . '/getting-started/installation.md',
        "# Installation\n\nHow to install Marko Framework step by step.",
    );
    file_put_contents(
        $docsPath . '/getting-started/quickstart.md',
        "# Quickstart\n\nFirst steps with the framework after installation.",
    );
    file_put_contents($docsPath . '/index.md', "# Welcome\n\nMarko documentation home.");

    $indexPath = $tempDir . '/docs.sqlite';
    $repo = new MarkdownRepository($docsPath);
    buildFtsIndexDirectly($repo, $indexPath);

    $embedder = new QueryEmbedder($runtime);
    $search = new VecSearch(
        repository: $repo,
        runtime: $runtime,
        embedder: $embedder,
        indexPath: $indexPath,
    );

    return [$search, $tempDir];
}

// Builds FTS5 index using a plain PDO (no sqlite-vec extension needed).
function buildFtsIndexDirectly(MarkdownRepository $repo, string $indexPath): void
{
    $pdo = new PDO('sqlite:' . $indexPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec(
        "CREATE VIRTUAL TABLE docs_fts USING fts5(page_id UNINDEXED, chunk_id UNINDEXED, title, content, tokenize='porter unicode61')",
    );
    $pdo->exec('CREATE TABLE docs_meta (page_id TEXT PRIMARY KEY, url TEXT, section TEXT, title TEXT)');

    $insertFts = $pdo->prepare(
        'INSERT INTO docs_fts (page_id, chunk_id, title, content) VALUES (:page_id, :chunk_id, :title, :content)',
    );
    $insertMeta = $pdo->prepare(
        'INSERT INTO docs_meta (page_id, url, section, title) VALUES (:page_id, :url, :section, :title)',
    );

    $chunkId = 0;
    $pdo->beginTransaction();

    foreach ($repo->listAllPages() as $pageId) {
        $raw = $repo->getRawMarkdown($pageId);
        $title = preg_match('/^#\s+(.+)$/m', $raw, $m) ? trim($m[1]) : basename($pageId);
        $body = preg_replace('/^---\s*\n.*?\n---\s*\n/s', '', $raw, 1) ?? $raw;

        $insertMeta->execute(['page_id' => $pageId, 'url' => '/' . $pageId, 'section' => 'root', 'title' => $title]);
        $chunkId++;
        $insertFts->execute(['page_id' => $pageId, 'chunk_id' => $chunkId, 'title' => $title, 'content' => $body]);
    }

    $pdo->commit();
}

// Builds a minimal FTS5 + docs_meta index without vec table for environments without the model
function buildFtsOnlyIndex(VecRuntime $runtime, MarkdownRepository $repo, string $indexPath): void
{
    $pdo = $runtime->openConnection($indexPath);
    $pdo->exec(
        "CREATE VIRTUAL TABLE docs_fts USING fts5(page_id UNINDEXED, chunk_id UNINDEXED, title, content, tokenize='porter unicode61')",
    );
    $pdo->exec('CREATE TABLE docs_meta (page_id TEXT PRIMARY KEY, url TEXT, section TEXT, title TEXT)');

    $insertFts = $pdo->prepare(
        'INSERT INTO docs_fts (page_id, chunk_id, title, content) VALUES (:page_id, :chunk_id, :title, :content)',
    );
    $insertMeta = $pdo->prepare(
        'INSERT INTO docs_meta (page_id, url, section, title) VALUES (:page_id, :url, :section, :title)',
    );

    $chunkId = 0;
    $pdo->beginTransaction();

    foreach ($repo->listAllPages() as $pageId) {
        $raw = $repo->getRawMarkdown($pageId);
        $title = preg_match('/^#\s+(.+)$/m', $raw, $m) ? trim($m[1]) : basename($pageId);
        $body = preg_replace('/^---\s*\n.*?\n---\s*\n/s', '', $raw, 1) ?? $raw;

        $insertMeta->execute(['page_id' => $pageId, 'url' => '/' . $pageId, 'section' => 'root', 'title' => $title]);
        $chunkId++;
        $insertFts->execute(['page_id' => $pageId, 'chunk_id' => $chunkId, 'title' => $title, 'content' => $body]);
    }

    $pdo->commit();
}
