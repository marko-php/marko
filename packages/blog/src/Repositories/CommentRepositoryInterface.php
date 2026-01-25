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
     * Get threaded comments for a post as a tree structure.
     * Returns only root-level comments with children populated.
     *
     * @return array<Comment>
     */
    public function getThreadedCommentsForPost(
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
     * Find all comments by author email.
     *
     * @return array<Comment>
     */
    public function findByAuthorEmail(
        string $email,
    ): array;

    /**
     * Calculate depth of a comment in the thread.
     * Returns 0 for root comments, 1 for direct replies, etc.
     */
    public function calculateDepth(
        int $commentId,
    ): int;
}
