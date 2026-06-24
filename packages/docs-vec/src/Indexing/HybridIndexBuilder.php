<?php

declare(strict_types=1);

namespace Marko\DocsVec\Indexing;

use Marko\Docs\Exceptions\DocsException;
use Marko\DocsMarkdown\MarkdownRepository;
use Marko\DocsVec\Runtime\VecRuntime;
use PDO;

class HybridIndexBuilder
{
    public function __construct(
        private MarkdownRepository $repository,
        private VecRuntime $runtime,
    ) {}

    /**
     * Build the search index. Produces a hybrid FTS5 + vector index when the
     * sqlite-vec extension, ONNX model, and transformers-php are all available;
     * otherwise degrades to an FTS5-only index (no docs_vec table, no embeddings)
     * so the driver still works on minimal setups.
     *
     * @return bool true if vector embeddings were included, false if FTS5-only
     *
     * @throws DocsException
     */
    public function build(string $outputPath): bool
    {
        $pages = $this->repository->listAllPages();

        if ($pages === []) {
            throw DocsException::searchFailed('No pages found in MarkdownRepository');
        }

        if (is_file($outputPath)) {
            @unlink($outputPath);
        }

        $dir = dirname($outputPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $vectorsEnabled = $this->runtime->isVectorSearchAvailable();

        $pdo = $vectorsEnabled
            ? $this->runtime->openConnection($outputPath)
            : $this->runtime->openPlainConnection($outputPath);

        $pdo->exec(
            "CREATE VIRTUAL TABLE docs_fts USING fts5(page_id UNINDEXED, chunk_id UNINDEXED, title, content, tokenize='porter unicode61')",
        );
        $pdo->exec('CREATE TABLE docs_meta (page_id TEXT PRIMARY KEY, url TEXT, section TEXT, title TEXT)');

        $insertVec = null;

        if ($vectorsEnabled) {
            $dim = $this->runtime->getEmbeddingDim();
            $pdo->exec(
                "CREATE VIRTUAL TABLE docs_vec USING vec0(chunk_id INTEGER PRIMARY KEY, embedding FLOAT[$dim])"
            );
            $insertVec = $pdo->prepare('INSERT INTO docs_vec (chunk_id, embedding) VALUES (:chunk_id, :embedding)');
        }

        $insertFts = $pdo->prepare(
            'INSERT INTO docs_fts (page_id, chunk_id, title, content) VALUES (:page_id, :chunk_id, :title, :content)',
        );
        $insertMeta = $pdo->prepare(
            'INSERT INTO docs_meta (page_id, url, section, title) VALUES (:page_id, :url, :section, :title)',
        );

        $chunkId = 0;
        $pdo->beginTransaction();

        foreach ($pages as $pageId) {
            $raw = $this->repository->getRawMarkdown($pageId);
            $title = $this->extractTitle($raw, $pageId);
            $body = $this->stripFrontmatter($raw);
            $section = $this->extractSection($pageId);

            $insertMeta->execute(
                ['page_id' => $pageId, 'url' => '/' . $pageId, 'section' => $section, 'title' => $title],
            );

            foreach ($this->chunkByHeading($body) as $chunk) {
                $chunkId++;
                $insertFts->execute(
                    ['page_id' => $pageId, 'chunk_id' => $chunkId, 'title' => $title, 'content' => $chunk],
                );

                if ($insertVec !== null) {
                    $embedding = $this->runtime->embed($chunk);
                    // vec0 requires an integer primary key; PDO binds array params
                    // as strings, which it rejects — bind chunk_id as PARAM_INT.
                    $insertVec->bindValue(':chunk_id', $chunkId, PDO::PARAM_INT);
                    $insertVec->bindValue(':embedding', json_encode($embedding), PDO::PARAM_STR);
                    $insertVec->execute();
                }
            }
        }

        $pdo->commit();

        return $vectorsEnabled;
    }

    /** @return list<string> chunks */
    private function chunkByHeading(string $markdown): array
    {
        $parts = preg_split('/^(#+\s+.*)$/m', $markdown, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false || count($parts) <= 1) {
            return [trim($markdown)];
        }

        $chunks = [];
        $current = '';

        foreach ($parts as $part) {
            if (preg_match('/^#+\s+/', $part)) {
                if ($current !== '') {
                    $chunks[] = trim($current);
                }

                $current = $part . "\n";
            } else {
                $current .= $part;
            }
        }

        if (trim($current) !== '') {
            $chunks[] = trim($current);
        }

        return array_values(array_filter($chunks, fn (string $c) => trim($c) !== ''));
    }

    private function extractTitle(
        string $markdown,
        string $fallback,
    ): string {
        if (preg_match('/^---\s*\n.*?title:\s*["\']?([^"\'\n]+)["\']?.*?\n---/s', $markdown, $m)) {
            return trim($m[1]);
        }

        if (preg_match('/^#\s+(.+)$/m', $markdown, $m)) {
            return trim($m[1]);
        }

        return basename($fallback);
    }

    private function stripFrontmatter(string $markdown): string
    {
        return preg_replace('/^---\s*\n.*?\n---\s*\n/s', '', $markdown, 1) ?? $markdown;
    }

    private function extractSection(string $pageId): string
    {
        $pos = strpos($pageId, '/');

        return $pos !== false ? substr($pageId, 0, $pos) : 'root';
    }
}
