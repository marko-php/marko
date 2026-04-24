<?php

declare(strict_types=1);

namespace Marko\DocsVec;

use Marko\Docs\Contract\DocsSearchInterface;
use Marko\Docs\Exceptions\DocsException;
use Marko\Docs\ValueObject\DocsNavEntry;
use Marko\Docs\ValueObject\DocsPage;
use Marko\Docs\ValueObject\DocsQuery;
use Marko\Docs\ValueObject\DocsResult;
use Marko\DocsMarkdown\MarkdownRepository;
use Marko\DocsVec\Query\QueryEmbedder;
use Marko\DocsVec\Runtime\VecRuntime;
use PDO;
use Throwable;

class VecSearch implements DocsSearchInterface
{
    private const int RRF_K = 60;

    private const int CANDIDATE_LIMIT = 50;

    private ?PDO $pdo = null;

    public function __construct(
        private MarkdownRepository $repository,
        private VecRuntime $runtime,
        private QueryEmbedder $embedder,
        private string $indexPath,
    ) {}

    public function driverName(): string
    {
        return 'docs-vec';
    }

    /**
     * @return list<DocsResult>
     *
     * @throws DocsException
     */
    public function search(DocsQuery $query): array
    {
        $pdo = $this->pdo();

        $ftsResults = $this->ftsSearch($pdo, $query->query, self::CANDIDATE_LIMIT);

        $vecResults = [];

        if ($this->runtime->isModelAvailable()) {
            try {
                $embedding = $this->embedder->embed($query->query);
                $vecResults = $this->vectorSearch($pdo, $embedding, self::CANDIDATE_LIMIT);
            } catch (Throwable) {
                // Fall back to FTS-only on embedding failure
            }
        }

        $fused = [];

        foreach ($ftsResults as $rank => $chunk) {
            $key = (string) $chunk['chunk_id'];
            $fused[$key] = ($fused[$key]['score'] ?? 0.0) + 1.0 / (self::RRF_K + $rank + 1);
            $fused[$key]['chunk'] = $chunk;
        }

        foreach ($vecResults as $rank => $chunk) {
            $key = (string) $chunk['chunk_id'];
            $fused[$key]['score'] = ($fused[$key]['score'] ?? 0.0) + 1.0 / (self::RRF_K + $rank + 1);
            $fused[$key]['chunk'] ??= $chunk;
        }

        uasort($fused, fn ($a, $b) => $b['score'] <=> $a['score']);
        $top = array_slice($fused, 0, $query->limit, true);

        $resultsByPage = [];

        foreach ($top as $entry) {
            $chunk = $entry['chunk'];
            $pageId = $chunk['page_id'];

            if (! isset($resultsByPage[$pageId])) {
                $resultsByPage[$pageId] = new DocsResult(
                    pageId: $pageId,
                    title: $chunk['title'] ?? $pageId,
                    excerpt: $chunk['excerpt'] ?? '',
                    score: $entry['score'],
                );
            }
        }

        return array_values($resultsByPage);
    }

    /**
     * @return list<array{chunk_id: int, page_id: string, title: string, excerpt: string}>
     */
    private function ftsSearch(PDO $pdo, string $query, int $limit): array
    {
        $stmt = $pdo->prepare("
            SELECT chunk_id, page_id, title,
                   snippet(docs_fts, 3, '<mark>', '</mark>', '...', 32) AS excerpt
            FROM docs_fts
            WHERE docs_fts MATCH :q
            ORDER BY bm25(docs_fts)
            LIMIT :limit
        ");
        $stmt->bindValue(':q', $query, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param list<float> $embedding
     *
     * @return list<array<string, mixed>>
     */
    private function vectorSearch(PDO $pdo, array $embedding, int $limit): array
    {
        $json = json_encode($embedding);
        $stmt = $pdo->prepare("
            SELECT v.chunk_id, f.page_id, f.title, '' AS excerpt, vec_distance_cosine(v.embedding, :emb) AS distance
            FROM docs_vec v
            JOIN docs_fts f ON f.chunk_id = v.chunk_id
            ORDER BY distance
            LIMIT :limit
        ");
        $stmt->bindValue(':emb', $json, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @throws DocsException
     */
    public function getPage(string $id): DocsPage
    {
        $pdo = $this->pdo();
        $stmt = $pdo->prepare('SELECT page_id, title, url FROM docs_meta WHERE page_id = :id');
        $stmt->execute([':id' => $id]);
        $meta = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! $meta) {
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
            $entries[] = new DocsNavEntry(
                id: $row['page_id'],
                title: $row['title'],
                path: $row['url'],
                depth: substr_count($row['page_id'], '/'),
            );
        }

        return $entries;
    }

    /**
     * @throws DocsException
     */
    private function pdo(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        if (! is_file($this->indexPath)) {
            throw DocsException::searchFailed("Index not found at $this->indexPath. Run \`marko docs-vec:build\` to generate it.");
        }

        try {
            $this->pdo = $this->runtime->openConnection($this->indexPath);
        } catch (Throwable $e) {
            throw DocsException::searchFailed('Failed to open index: ' . $e->getMessage());
        }

        return $this->pdo;
    }
}
