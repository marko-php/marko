<?php

declare(strict_types=1);

namespace Marko\DocsMarkdown;

use FilesystemIterator;
use Marko\DocsMarkdown\Exceptions\DocsMarkdownException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class MarkdownRepository
{
    public function __construct(
        private string $docsPath,
    ) {}

    public function getDocsPath(): string
    {
        return $this->docsPath;
    }

    /** @return list<string> page IDs (relative paths without .md extension) */
    public function listAllPages(): array
    {
        $pages = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->docsPath, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'md') {
                $relative = str_replace($this->docsPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $pages[] = str_replace([DIRECTORY_SEPARATOR, '.md'], ['/', ''], $relative);
            }
        }

        sort($pages);

        return array_values($pages);
    }

    /** @throws DocsMarkdownException */
    public function getRawMarkdown(string $id): string
    {
        $filePath = $this->docsPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $id) . '.md';

        if (!file_exists($filePath)) {
            throw DocsMarkdownException::pageNotFound($id, $this->docsPath);
        }

        return (string) file_get_contents($filePath);
    }
}
