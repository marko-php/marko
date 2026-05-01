<?php

declare(strict_types=1);

namespace Marko\DocsFts\Indexing;

use Marko\Docs\Exceptions\DocsException;
use Marko\DocsMarkdown\MarkdownRepository;
use PDO;

class FtsIndexBuilder
{
    public function __construct(
        private MarkdownRepository $repository,
    ) {}

    /** @throws DocsException */
    public function build(string $outputPath): void
    {
        $pages = $this->repository->listAllPages();

        if ($pages === []) {
            throw DocsException::searchFailed('No pages found in MarkdownRepository — check docs-markdown is installed');
        }

        if (is_file($outputPath)) {
            @unlink($outputPath);
        }

        $dir = dirname($outputPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $pdo = new PDO('sqlite:' . $outputPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->exec("CREATE VIRTUAL TABLE docs_fts USING fts5(
            page_id UNINDEXED,
            title,
            content,
            tokenize = 'porter unicode61'
        )");

        $pdo->exec('CREATE TABLE docs_meta (
            page_id TEXT PRIMARY KEY,
            url TEXT,
            section TEXT,
            title TEXT,
            last_updated INTEGER
        )');

        $insertFts = $pdo->prepare('INSERT INTO docs_fts (page_id, title, content) VALUES (:id, :title, :content)');
        $insertMeta = $pdo->prepare('INSERT INTO docs_meta (page_id, url, section, title, last_updated) VALUES (:id, :url, :section, :title, :ts)');

        $pdo->beginTransaction();

        foreach ($pages as $pageId) {
            $raw = $this->repository->getRawMarkdown($pageId);
            $title = $this->extractTitle($raw, $pageId);
            $content = $this->stripFrontmatter($raw);
            $section = $this->extractSection($pageId);

            $insertFts->execute(['id' => $pageId, 'title' => $title, 'content' => $content]);
            $insertMeta->execute([
                'id' => $pageId,
                'url' => '/' . $pageId,
                'section' => $section,
                'title' => $title,
                'ts' => time(),
            ]);
        }

        $pdo->commit();
    }

    private function extractTitle(string $markdown, string $fallback): string
    {
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
