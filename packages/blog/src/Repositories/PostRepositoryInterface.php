<?php

declare(strict_types=1);

namespace Marko\Blog\Repositories;

use Marko\Blog\Entity\Post;
use Marko\Blog\Enum\PostStatus;
use Marko\Database\Repository\RepositoryInterface;

interface PostRepositoryInterface extends RepositoryInterface
{
    /**
     * Find a post by its slug.
     */
    public function findBySlug(
        string $slug,
    ): ?Post;

    /**
     * Find all published posts.
     *
     * @return array<Post>
     */
    public function findPublished(): array;

    /**
     * Find posts by status.
     *
     * @return array<Post>
     */
    public function findByStatus(
        PostStatus $status,
    ): array;

    /**
     * Find posts by author.
     *
     * @return array<Post>
     */
    public function findByAuthor(
        int $authorId,
    ): array;

    /**
     * Find scheduled posts that are due for publishing.
     *
     * @return array<Post>
     */
    public function findScheduledPostsDue(): array;

    /**
     * Count posts by author.
     */
    public function countByAuthor(
        int $authorId,
    ): int;

    /**
     * Check if a slug is unique within the posts table.
     *
     * @param string $slug The slug to check
     * @param int|null $excludeId Optional post ID to exclude (for updates)
     */
    public function isSlugUnique(
        string $slug,
        ?int $excludeId = null,
    ): bool;
}
