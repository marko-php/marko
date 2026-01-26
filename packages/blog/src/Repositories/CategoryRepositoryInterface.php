<?php

declare(strict_types=1);

namespace Marko\Blog\Repositories;

use Marko\Blog\Entity\Category;
use Marko\Blog\Entity\Post;
use Marko\Database\Repository\RepositoryInterface;

interface CategoryRepositoryInterface extends RepositoryInterface
{
    /**
     * Find a category by its slug.
     */
    public function findBySlug(
        string $slug,
    ): ?Category;

    /**
     * Check if a slug is unique within the categories table.
     */
    public function isSlugUnique(
        string $slug,
        ?int $excludeId = null,
    ): bool;

    /**
     * Find all child categories of a parent.
     *
     * @return array<Category>
     */
    public function findChildren(
        Category $parent,
    ): array;

    /**
     * Get the full path from root to the given category.
     *
     * @return array<Category>
     */
    public function getPath(
        Category $category,
    ): array;

    /**
     * Find all root categories (categories with no parent).
     *
     * @return array<Category>
     */
    public function findRoots(): array;

    /**
     * Get all posts for a category.
     *
     * @return array<Post>
     */
    public function getPostsForCategory(
        int $categoryId,
    ): array;

    /**
     * Get all descendant category IDs (children, grandchildren, etc.).
     *
     * @return array<int>
     */
    public function getDescendantIds(
        int $categoryId,
    ): array;
}
