<?php

declare(strict_types=1);

namespace Marko\Blog\Services;

use Marko\Blog\Dto\PaginatedResult;
use Marko\Blog\Entity\PostInterface;

interface SeoMetaServiceInterface
{
    public function getCanonicalUrl(
        string $path,
    ): string;

    public function getPostCanonicalUrl(
        PostInterface $post,
    ): string;

    public function getArchiveCanonicalUrl(
        string $type,
        string $slug,
        int $page = 1,
    ): string;

    public function getSearchCanonicalUrl(
        string $query,
        int $page = 1,
    ): string;

    public function getMetaDescription(
        ?string $content,
    ): string;

    public function getArchiveMetaDescription(
        string $type,
        string $name,
    ): string;

    /**
     * @param PaginatedResult<mixed> $paginatedResult
     */
    public function getPrevLink(
        string $basePath,
        PaginatedResult $paginatedResult,
    ): ?string;

    /**
     * @param PaginatedResult<mixed> $paginatedResult
     */
    public function getNextLink(
        string $basePath,
        PaginatedResult $paginatedResult,
    ): ?string;

    public function getPageTitle(
        string $title,
    ): string;
}
