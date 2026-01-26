<?php

declare(strict_types=1);

namespace Marko\Blog\Repositories;

use Marko\Blog\Entity\Category;
use Marko\Blog\Entity\Post;
use Marko\Blog\Entity\Tag;
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
     * Find published posts with pagination, ordered by published_at DESC.
     *
     * @return array<Post>
     */
    public function findPublishedPaginated(
        int $limit,
        int $offset,
    ): array;

    /**
     * Count all published posts.
     */
    public function countPublished(): int;

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
     * Find published posts by author with pagination.
     *
     * @return array<Post>
     */
    public function findPublishedByAuthor(
        int $authorId,
        int $limit,
        int $offset,
    ): array;

    /**
     * Count published posts by author.
     */
    public function countPublishedByAuthor(
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

    /**
     * Find published posts by tag with pagination.
     *
     * @return array<Post>
     */
    public function findPublishedByTag(
        int $tagId,
        int $limit,
        int $offset,
    ): array;

    /**
     * Count published posts by tag.
     */
    public function countPublishedByTag(
        int $tagId,
    ): int;

    /**
     * Find published posts by category with pagination.
     *
     * @return array<Post>
     */
    public function findPublishedByCategory(
        int $categoryId,
        int $limit,
        int $offset,
    ): array;

    /**
     * Count published posts by category.
     */
    public function countPublishedByCategory(
        int $categoryId,
    ): int;

    /**
     * Find published posts by multiple categories with pagination.
     *
     * @param array<int> $categoryIds
     * @return array<Post>
     */
    public function findPublishedByCategories(
        array $categoryIds,
        int $limit,
        int $offset,
    ): array;

    /**
     * Count published posts by multiple categories.
     *
     * @param array<int> $categoryIds
     */
    public function countPublishedByCategories(
        array $categoryIds,
    ): int;

    /**
     * Attach a category to a post.
     */
    public function attachCategory(
        int $postId,
        int $categoryId,
    ): void;

    /**
     * Detach a category from a post.
     */
    public function detachCategory(
        int $postId,
        int $categoryId,
    ): void;

    /**
     * Attach a tag to a post.
     */
    public function attachTag(
        int $postId,
        int $tagId,
    ): void;

    /**
     * Detach a tag from a post.
     */
    public function detachTag(
        int $postId,
        int $tagId,
    ): void;

    /**
     * Get all categories for a post.
     *
     * @return array<Category>
     */
    public function getCategoriesForPost(
        int $postId,
    ): array;

    /**
     * Get all tags for a post.
     *
     * @return array<Tag>
     */
    public function getTagsForPost(
        int $postId,
    ): array;

    /**
     * Sync categories for a post, replacing all existing.
     *
     * @param array<int> $categoryIds
     */
    public function syncCategories(
        int $postId,
        array $categoryIds,
    ): void;

    /**
     * Sync tags for a post, replacing all existing.
     *
     * @param array<int> $tagIds
     */
    public function syncTags(
        int $postId,
        array $tagIds,
    ): void;
}
