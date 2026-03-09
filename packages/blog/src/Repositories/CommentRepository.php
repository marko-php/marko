<?php

declare(strict_types=1);

namespace Marko\Blog\Repositories;

use Marko\Blog\Entity\Comment;
use Marko\Blog\Enum\CommentStatus;
use Marko\Blog\Events\Comment\CommentCreated;
use Marko\Blog\Events\Comment\CommentDeleted;
use Marko\Database\Entity\Entity;
use Marko\Database\Repository\Repository;

/**
 * @extends Repository<Comment>
 */
class CommentRepository extends Repository implements CommentRepositoryInterface
{
    protected const string ENTITY_CLASS = Comment::class;

    /**
     * Find a comment by its ID.
     */
    public function find(
        int $id,
    ): ?Comment {
        $entity = parent::find($id);

        return $entity instanceof Comment ? $entity : null;
    }

    /**
     * Save a comment, dispatching appropriate events.
     */
    public function save(
        Entity $entity,
    ): void {
        if (!$entity instanceof Comment) {
            parent::save($entity);

            return;
        }

        $isNew = $entity->id === null;

        parent::save($entity);

        if ($isNew && $this->eventDispatcher !== null) {
            $this->eventDispatcher->dispatch(new CommentCreated(
                comment: $entity,
                post: $entity->getPost(),
            ));
        }
    }

    /**
     * Delete a comment, dispatching appropriate events.
     */
    public function delete(
        Entity $entity,
    ): void {
        if (!$entity instanceof Comment) {
            parent::delete($entity);

            return;
        }

        parent::delete($entity);

        $this->eventDispatcher?->dispatch(new CommentDeleted(
            comment: $entity,
            post: $entity->getPost(),
        ));
    }

    /**
     * Find all verified comments for a post.
     *
     * @return array<Comment>
     */
    public function findVerifiedForPost(
        int $postId,
    ): array {
        $sql = sprintf(
            'SELECT * FROM %s WHERE post_id = ? AND status = ? ORDER BY created_at ASC',
            $this->metadata->tableName,
        );

        $rows = $this->connection->query($sql, [
            $postId,
            CommentStatus::Verified->value,
        ]);

        return array_map(
            fn (array $row): Comment => $this->hydrator->hydrate(
                static::ENTITY_CLASS,
                $row,
                $this->metadata,
            ),
            $rows,
        );
    }

    /**
     * Find all pending comments for a post.
     *
     * @return array<Comment>
     */
    public function findPendingForPost(
        int $postId,
    ): array {
        $sql = sprintf(
            'SELECT * FROM %s WHERE post_id = ? AND status = ?',
            $this->metadata->tableName,
        );

        $rows = $this->connection->query($sql, [
            $postId,
            CommentStatus::Pending->value,
        ]);

        return array_map(
            fn (array $row): Comment => $this->hydrator->hydrate(
                static::ENTITY_CLASS,
                $row,
                $this->metadata,
            ),
            $rows,
        );
    }

    /**
     * Count total comments for a post (all statuses).
     */
    public function countForPost(
        int $postId,
    ): int {
        $sql = sprintf(
            'SELECT COUNT(*) as count FROM %s WHERE post_id = ?',
            $this->metadata->tableName,
        );

        $result = $this->connection->query($sql, [$postId]);

        return (int) ($result[0]['count'] ?? 0);
    }

    /**
     * Count verified comments for a post.
     */
    public function countVerifiedForPost(
        int $postId,
    ): int {
        $sql = sprintf(
            'SELECT COUNT(*) as count FROM %s WHERE post_id = ? AND status = ?',
            $this->metadata->tableName,
        );

        $result = $this->connection->query($sql, [
            $postId,
            CommentStatus::Verified->value,
        ]);

        return (int) ($result[0]['count'] ?? 0);
    }

    /**
     * Find all comments by author email.
     *
     * @return array<Comment>
     */
    public function findByEmail(
        string $email,
    ): array {
        $sql = sprintf(
            'SELECT * FROM %s WHERE email = ?',
            $this->metadata->tableName,
        );

        $rows = $this->connection->query($sql, [$email]);

        return array_map(
            fn (array $row): Comment => $this->hydrator->hydrate(
                static::ENTITY_CLASS,
                $row,
                $this->metadata,
            ),
            $rows,
        );
    }
}
