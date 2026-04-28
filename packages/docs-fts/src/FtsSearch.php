<?php

declare(strict_types=1);

namespace Marko\DocsFts;

use Marko\Docs\Contract\DocsSearchInterface;
use Marko\Docs\Exceptions\DocsException;
use Marko\Docs\ValueObject\DocsNavEntry;
use Marko\Docs\ValueObject\DocsPage;
use Marko\Docs\ValueObject\DocsQuery;
use Marko\Docs\ValueObject\DocsResult;
use Marko\DocsMarkdown\MarkdownRepository;
use PDO;

class FtsSearch implements DocsSearchInterface
{
    private ?PDO $pdo = null;

    public function __construct(
        private MarkdownRepository $repository,
        private string $indexPath,
    ) {}

    /**
     * @return list<DocsResult>
     *
     * @throws DocsException
     */
    public function search(DocsQuery $query): array
    {
        $pdo = $this->pdo();
        $stmt = $pdo->prepare("
            SELECT page_id, title,
                   snippet(docs_fts, 2, '<mark>', '</mark>', '...', 32) AS excerpt,
                   bm25(docs_fts) AS score
            FROM docs_fts
            WHERE docs_fts MATCH :q
            ORDER BY bm25(docs_fts)
            LIMIT :limit
        ");
        $stmt->bindValue(':q', $query->query, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $query->limit, PDO::PARAM_INT);
        $stmt->execute();

        $results = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $results[] = new DocsResult(
                pageId: $row['page_id'],
                title: $row['title'],
                excerpt: $row['excerpt'],
                score: -((float) $row['score']),
            );
        }

        return $results;
    }

    /** @throws DocsException */
    public function getPage(string $id): DocsPage
    {
        $pdo = $this->pdo();
        $stmt = $pdo->prepare('SELECT page_id, title, url FROM docs_meta WHERE page_id = :id');
        $stmt->execute([':id' => $id]);
        $meta = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$meta) {
            throw DocsException::pageNotFound($id);
        }

        return new DocsPage(
            id: $meta['page_id'],
            title: $meta['title'],
            content: $this->repository->getRawMarkdown($id),
            path: $meta['url'],
        );
    }

    /**
     * @return list<DocsNavEntry>
     *
     * @throws DocsException
     */
    public function listNav(): array
    {
        $pdo = $this->pdo();
        $stmt = $pdo->query('SELECT page_id, title, url FROM docs_meta ORDER BY page_id');
        $entries = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $depth = substr_count($row['page_id'], '/');
            $entries[] = new DocsNavEntry(
                id: $row['page_id'],
                title: $row['title'],
                path: $row['url'],
                depth: $depth,
            );
        }

        return $entries;
    }

    public function driverName(): string
    {
        return 'docs-fts';
    }

    /** @throws DocsException */
    private function pdo(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        if (!is_file($this->indexPath)) {
            throw DocsException::searchFailed("FTS5 index not found at $this->indexPath. Run `marko docs-fts:build` to generate it.");
        }

        $this->pdo = new PDO('sqlite:' . $this->indexPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $this->pdo;
    }
}
