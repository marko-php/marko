<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Docs\Exceptions\DocsException;
use Marko\DocsFts\Commands\BuildIndexCommand;
use Marko\DocsFts\Indexing\FtsIndexBuilder;
use Marko\DocsMarkdown\MarkdownRepository;

/**
 * Create a fake MarkdownRepository with canned pages.
 *
 * @param array<string, string> $pages map of pageId => markdown content
 */
function makeFakeRepository(array $pages): MarkdownRepository
{
    return new class ($pages) extends MarkdownRepository
    {
        /** @param array<string, string> $pages */
        public function __construct(
            private readonly array $pages,
        ) {}

        public function listAllPages(): array
        {
            return array_keys($this->pages);
        }

        public function getRawMarkdown(string $id): string
        {
            return $this->pages[$id] ?? '';
        }

        public function getDocsPath(): string
        {
            return '/fake/docs';
        }
    };
}

/**
 * Create a temporary SQLite path cleaned up after the test.
 */
function tempDbPath(): string
{
    return sys_get_temp_dir() . '/docs-fts-test-' . uniqid() . '.sqlite';
}

it('reads all pages from MarkdownRepository', function (): void {
    $pages = [
        'intro/index' => "# Introduction\nWelcome to Marko.",
        'guide/install' => "# Installation\nRun composer install.",
    ];
    $repo = makeFakeRepository($pages);
    $builder = new FtsIndexBuilder($repo);
    $dbPath = tempDbPath();

    $builder->build($dbPath);

    $pdo = new PDO('sqlite:' . $dbPath);
    $count = (int) $pdo->query('SELECT COUNT(*) FROM docs_meta')->fetchColumn();

    expect($count)->toBe(2);

    @unlink($dbPath);
});

it('writes FTS5 virtual table docs_fts with porter unicode61 tokenizer', function (): void {
    $repo = makeFakeRepository([
        'intro/index' => "# Introduction\nWelcome to Marko.",
    ]);
    $builder = new FtsIndexBuilder($repo);
    $dbPath = tempDbPath();

    $builder->build($dbPath);

    $pdo = new PDO('sqlite:' . $dbPath);
    $sql = (string) $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='docs_fts'")->fetchColumn();

    expect($sql)->toContain('fts5')
        ->and($sql)->toContain('porter')
        ->and($sql)->toContain('unicode61');

    @unlink($dbPath);
});

it('writes companion docs_meta table with page metadata', function (): void {
    $repo = makeFakeRepository([
        'guide/install' => "# Installation\nRun composer install.",
    ]);
    $builder = new FtsIndexBuilder($repo);
    $dbPath = tempDbPath();

    $builder->build($dbPath);

    $pdo = new PDO('sqlite:' . $dbPath);
    $row = $pdo->query("SELECT page_id, url, section, title FROM docs_meta WHERE page_id = 'guide/install'")->fetch(PDO::FETCH_ASSOC);

    expect($row)->toBeArray()
        ->and($row['page_id'])->toBe('guide/install')
        ->and($row['url'])->toBe('/guide/install')
        ->and($row['section'])->toBe('guide')
        ->and($row['title'])->toBe('Installation');

    @unlink($dbPath);
});

it('produces queryable BM25-ranked results', function (): void {
    $repo = makeFakeRepository([
        'intro/index' => "# Introduction\nWelcome to Marko framework.",
        'guide/install' => "# Installation\nInstall via composer.",
        'guide/config' => "# Configuration\nConfigure your application.",
    ]);
    $builder = new FtsIndexBuilder($repo);
    $dbPath = tempDbPath();

    $builder->build($dbPath);

    $pdo = new PDO('sqlite:' . $dbPath);
    $results = $pdo->query("SELECT page_id FROM docs_fts WHERE docs_fts MATCH 'install' ORDER BY rank")->fetchAll(PDO::FETCH_COLUMN);

    expect($results)->not->toBeEmpty()
        ->and($results[0])->toBe('guide/install');

    @unlink($dbPath);
});

it('ranks exact title matches higher than body-only matches', function (): void {
    $repo = makeFakeRepository([
        'guide/install' => "# Installation\nThis page is about the installation process.",
        'guide/config' => "# Configuration\nSee installation notes in the body for reference.",
    ]);
    $builder = new FtsIndexBuilder($repo);
    $dbPath = tempDbPath();

    $builder->build($dbPath);

    $pdo = new PDO('sqlite:' . $dbPath);
    // Use weighted BM25: title weight=10, content weight=1
    $results = $pdo->query(
        "SELECT page_id FROM docs_fts WHERE docs_fts MATCH 'installation' ORDER BY bm25(docs_fts, 0, 10, 1)",
    )->fetchAll(PDO::FETCH_COLUMN);

    expect($results)->not->toBeEmpty()
        ->and($results[0])->toBe('guide/install');

    @unlink($dbPath);
});

it('overwrites existing docs.sqlite on rebuild idempotently', function (): void {
    $pages = [
        'intro/index' => "# Introduction\nWelcome.",
        'guide/install' => "# Installation\nInstall it.",
    ];
    $repo = makeFakeRepository($pages);
    $builder = new FtsIndexBuilder($repo);
    $dbPath = tempDbPath();

    // First build
    $builder->build($dbPath);
    // Second build — should not throw, should overwrite
    $builder->build($dbPath);

    $pdo = new PDO('sqlite:' . $dbPath);
    $count = (int) $pdo->query('SELECT COUNT(*) FROM docs_meta')->fetchColumn();

    expect($count)->toBe(2);

    @unlink($dbPath);
});

it('throws DocsException when MarkdownRepository returns zero pages', function (): void {
    $repo = makeFakeRepository([]);
    $builder = new FtsIndexBuilder($repo);
    $dbPath = tempDbPath();

    expect(fn () => $builder->build($dbPath))->toThrow(DocsException::class);

    @unlink($dbPath);
});

it('registers a #[Command(name: \'docs-fts:build\')] CLI command that invokes the builder and reports output path', function (): void {
    $reflection = new ReflectionClass(BuildIndexCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->name)->toBe('docs-fts:build');

    expect($reflection->implementsInterface(CommandInterface::class))->toBeTrue();
});
