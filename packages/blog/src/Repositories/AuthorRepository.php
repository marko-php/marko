<?php

declare(strict_types=1);

namespace Marko\Blog\Repositories;

use Closure;
use DateTimeImmutable;
use Marko\Blog\Entity\Author;
use Marko\Blog\Events\Author\AuthorCreated;
use Marko\Blog\Events\Author\AuthorDeleted;
use Marko\Blog\Events\Author\AuthorUpdated;
use Marko\Blog\Exceptions\AuthorHasPostsException;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Repository\Repository;

class AuthorRepository extends Repository implements AuthorRepositoryInterface
{
    protected const string ENTITY_CLASS = Author::class;

    public function __construct(
        ConnectionInterface $connection,
        EntityMetadataFactory $metadataFactory,
        EntityHydrator $hydrator,
        ?Closure $queryBuilderFactory = null,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        parent::__construct($connection, $metadataFactory, $hydrator, $queryBuilderFactory);
    }

    /**
     * Save an author entity and dispatch lifecycle events.
     */
    public function save(
        Entity $entity,
    ): void {
        $isNew = $this->hydrator->isNew($entity, $this->metadata);

        parent::save($entity);

        if ($this->eventDispatcher !== null && $entity instanceof Author) {
            $timestamp = new DateTimeImmutable();

            if ($isNew) {
                $this->eventDispatcher->dispatch(new AuthorCreated($entity, $timestamp));
            } else {
                $this->eventDispatcher->dispatch(new AuthorUpdated($entity, $timestamp));
            }
        }
    }

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

        if ($this->eventDispatcher !== null) {
            $this->eventDispatcher->dispatch(new AuthorDeleted($entity, new DateTimeImmutable()));
        }
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
