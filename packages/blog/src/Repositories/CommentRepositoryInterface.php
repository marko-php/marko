<?php

declare(strict_types=1);

namespace Marko\Blog\Repositories;

use Marko\Blog\Entity\Comment;
use Marko\Database\Repository\RepositoryInterface;

interface CommentRepositoryInterface extends RepositoryInterface
{
    /**
     * Find a comment by its ID.
     */
    public function find(
        int $id,
    ): ?Comment;

    /**
     * Find all verified comments for a post.
     *
     * @return array<Comment>
     */
    public function findVerifiedForPost(
        int $postId,
    ): array;

    /**
     * Find all pending comments for a post.
     *
     * @return array<Comment>
     */
    public function findPendingForPost(
        int $postId,
    ): array;

    /**
     * Count total comments for a post (all statuses).
     */
    public function countForPost(
        int $postId,
    ): int;

    /**
     * Count verified comments for a post.
     */
    public function countVerifiedForPost(
        int $postId,
    ): int;

    /**
     * Find all comments by email.
     *
     * @return array<Comment>
     */
    public function findByEmail(
        string $email,
    ): array;
}
