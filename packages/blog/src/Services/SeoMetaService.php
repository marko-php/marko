<?php

declare(strict_types=1);

namespace Marko\Blog\Services;

use Marko\Blog\Config\BlogConfigInterface;
use Marko\Blog\Dto\PaginatedResult;
use Marko\Blog\Entity\PostInterface;

class SeoMetaService implements SeoMetaServiceInterface
{
    public function __construct(
        private readonly BlogConfigInterface $config,
        private readonly string $baseUrl,
        private readonly string $siteName,
    ) {}

    public function getCanonicalUrl(
        string $path,
    ): string {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
    }

    public function getPostCanonicalUrl(
        PostInterface $post,
    ): string {
        $path = $this->config->getRoutePrefix() . '/' . $post->getSlug();

        return $this->getCanonicalUrl($path);
    }

    public function getArchiveCanonicalUrl(
        string $type,
        string $slug,
        int $page = 1,
    ): string {
        $path = $this->config->getRoutePrefix() . '/' . $type . '/' . $slug;

        if ($page > 1) {
            $path .= '/page/' . $page;
        }

        return $this->getCanonicalUrl($path);
    }

    public function getSearchCanonicalUrl(
        string $query,
        int $page = 1,
    ): string {
        $path = $this->config->getRoutePrefix() . '/search?q=' . urlencode($query);

        if ($page > 1) {
            $path .= '&page=' . $page;
        }

        return $this->getCanonicalUrl($path);
    }

    private const int MAX_META_DESCRIPTION_LENGTH = 160;

    public function getMetaDescription(
        ?string $content,
    ): string {
        if ($content === null || $content === '') {
            return '';
        }

        if (mb_strlen($content) <= self::MAX_META_DESCRIPTION_LENGTH) {
            return $content;
        }

        // Truncate and add ellipsis
        $truncated = mb_substr($content, 0, self::MAX_META_DESCRIPTION_LENGTH - 3);

        // Try to break at word boundary
        $lastSpace = mb_strrpos($truncated, ' ');
        if ($lastSpace !== false && $lastSpace > self::MAX_META_DESCRIPTION_LENGTH - 30) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        return $truncated . '...';
    }

    public function getArchiveMetaDescription(
        string $type,
        string $name,
    ): string {
        return match ($type) {
            'category' => "Browse all posts in the $name category.",
            'tag' => "Browse all posts tagged with $name.",
            'author' => "Browse all posts by $name.",
            default => "Browse all posts in $name.",
        };
    }

    /**
     * @param PaginatedResult<mixed> $paginatedResult
     */
    public function getPrevLink(
        string $basePath,
        PaginatedResult $paginatedResult,
    ): ?string {
        if (!$paginatedResult->hasPreviousPage) {
            return null;
        }

        $prevPage = $paginatedResult->getPreviousPage();
        $path = $basePath;

        if ($prevPage !== null && $prevPage > 1) {
            $path .= '/page/' . $prevPage;
        }

        return $this->getCanonicalUrl($path);
    }

    /**
     * @param PaginatedResult<mixed> $paginatedResult
     */
    public function getNextLink(
        string $basePath,
        PaginatedResult $paginatedResult,
    ): ?string {
        if (!$paginatedResult->hasNextPage) {
            return null;
        }

        $nextPage = $paginatedResult->getNextPage();
        $path = $basePath . '/page/' . $nextPage;

        return $this->getCanonicalUrl($path);
    }

    public function getPageTitle(
        string $title,
    ): string {
        return $title . ' | ' . $this->siteName;
    }
}
