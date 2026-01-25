<?php

declare(strict_types=1);

namespace Marko\Blog\Repositories;

use Marko\Blog\Entity\Tag;
use Marko\Database\Repository\RepositoryInterface;

interface TagRepositoryInterface extends RepositoryInterface
{
    /**
     * Find a tag by its slug.
     */
    public function findBySlug(
        string $slug,
    ): ?Tag;

    /**
     * Find tags by partial name match.
     *
     * @return array<Tag>
     */
    public function findByNameLike(
        string $name,
    ): array;

    /**
     * Check if a slug is unique within the tags table.
     *
     * @param string $slug The slug to check
     * @param int|null $excludeId Optional ID to exclude (for updates)
     * @return bool True if the slug is unique, false otherwise
     */
    public function isSlugUnique(
        string $slug,
        ?int $excludeId = null,
    ): bool;
}
