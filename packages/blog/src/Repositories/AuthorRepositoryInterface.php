<?php

declare(strict_types=1);

namespace Marko\Blog\Repositories;

use Marko\Blog\Entity\Author;
use Marko\Database\Repository\RepositoryInterface;

interface AuthorRepositoryInterface extends RepositoryInterface
{
    /**
     * Find an author by their slug.
     */
    public function findBySlug(
        string $slug,
    ): ?Author;

    /**
     * Find an author by their email.
     */
    public function findByEmail(
        string $email,
    ): ?Author;

    /**
     * Check if a slug is unique within the authors table.
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
