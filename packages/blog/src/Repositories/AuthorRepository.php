<?php

declare(strict_types=1);

namespace Marko\Blog\Repositories;

use Marko\Blog\Entity\Author;
use Marko\Blog\Exceptions\AuthorHasPostsException;
use Marko\Database\Entity\Entity;
use Marko\Database\Repository\Repository;

class AuthorRepository extends Repository implements AuthorRepositoryInterface
{
    protected const string ENTITY_CLASS = Author::class;

    /**
     * Find an author by their slug.
     */
    public function findBySlug(
        string $slug,
    ): ?Author {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * Find an author by their email.
     */
    public function findByEmail(
        string $email,
    ): ?Author {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * Check if a slug is unique within the authors table.
     *
     * @param string $slug The slug to check
     * @param int|null $excludeId Optional author ID to exclude (for updates)
     */
    public function isSlugUnique(
        string $slug,
        ?int $excludeId = null,
    ): bool {
        $sql = sprintf(
            'SELECT * FROM %s WHERE slug = ?',
            $this->metadata->tableName,
        );
        $bindings = [$slug];

        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $bindings[] = $excludeId;
        }

        $rows = $this->connection->query($sql, $bindings);

        return count($rows) === 0;
    }

    /**
     * Delete an author entity.
     *
     * @throws AuthorHasPostsException if the author has associated posts
     */
    public function delete(
        Entity $entity,
    ): void {
        if (!$entity instanceof Author) {
            parent::delete($entity);

            return;
        }

        // Check if author has associated posts
        $postCount = $this->countAssociatedPosts($entity->id);

        if ($postCount > 0) {
            throw AuthorHasPostsException::cannotDelete($entity->name, $postCount);
        }

        parent::delete($entity);
    }

    /**
     * Count the number of posts associated with an author.
     */
    private function countAssociatedPosts(
        int $authorId,
    ): int {
        $sql = 'SELECT COUNT(*) as count FROM posts WHERE author_id = ?';
        $result = $this->connection->query($sql, [$authorId]);

        return (int) ($result[0]['count'] ?? 0);
    }
}
