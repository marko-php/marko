<?php

declare(strict_types=1);

namespace Marko\Blog\Services;

use Marko\Blog\Entity\Comment;

interface CommentThreadingServiceInterface
{
    /**
     * Get threaded comments for a post as a tree structure.
     * Returns only root-level comments with children populated.
     *
     * @return array<Comment>
     */
    public function getThreadedComments(
        int $postId,
    ): array;

    /**
     * Calculate depth of a comment in the thread.
     * Returns 0 for root comments, 1 for direct replies, etc.
     * Returns 0 when comment does not exist.
     */
    public function calculateDepth(
        int $commentId,
    ): int;
}
