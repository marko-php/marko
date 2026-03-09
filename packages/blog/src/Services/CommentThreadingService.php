<?php

declare(strict_types=1);

namespace Marko\Blog\Services;

use Marko\Blog\Config\BlogConfigInterface;
use Marko\Blog\Entity\Comment;
use Marko\Blog\Repositories\CommentRepositoryInterface;

readonly class CommentThreadingService implements CommentThreadingServiceInterface
{
    public function __construct(
        private CommentRepositoryInterface $commentRepository,
        private BlogConfigInterface $blogConfig,
    ) {}

    /**
     * Get threaded comments for a post as a tree structure.
     *
     * @return array<Comment>
     */
    public function getThreadedComments(
        int $postId,
    ): array {
        $comments = $this->commentRepository->findVerifiedForPost($postId);

        return $this->buildTree($comments);
    }

    /**
     * Calculate depth of a comment in the thread.
     * Returns 0 for root comments, 1 for direct replies, etc.
     * Returns 0 when comment does not exist.
     */
    public function calculateDepth(
        int $commentId,
    ): int {
        $comment = $this->commentRepository->find($commentId);

        if ($comment === null) {
            return 0;
        }

        $depth = 0;
        $currentParentId = $comment->parentId;

        while ($currentParentId !== null) {
            $depth++;
            $parent = $this->commentRepository->find($currentParentId);
            $currentParentId = $parent?->parentId;
        }

        return $depth;
    }

    /**
     * Build a tree structure from a flat list of comments.
     * Respects max depth configuration - comments exceeding max depth are
     * flattened to the max depth level.
     *
     * @param array<Comment> $comments
     * @return array<Comment>
     */
    private function buildTree(
        array $comments,
    ): array {
        $maxDepth = $this->blogConfig->getCommentMaxDepth();

        /** @var array<int, Comment> $commentMap */
        $commentMap = [];

        /** @var array<int, int> $depthMap */
        $depthMap = [];

        // First pass: index all comments by ID and initialize children
        foreach ($comments as $comment) {
            if ($comment->id !== null) {
                $commentMap[$comment->id] = $comment;
                $comment->setChildren([]);
            }
        }

        // Second pass: calculate depth for each comment
        foreach ($comments as $comment) {
            if ($comment->id !== null) {
                $depthMap[$comment->id] = $this->calculateDepthFromMap($comment, $commentMap);
            }
        }

        /** @var array<Comment> $rootComments */
        $rootComments = [];

        // Third pass: build tree structure respecting max depth
        foreach ($comments as $comment) {
            if ($comment->parentId === null) {
                // Root comment
                $rootComments[] = $comment;
            } elseif (isset($commentMap[$comment->parentId])) {
                // Find the appropriate parent based on max depth
                $parent = $this->findEffectiveParent(
                    $comment,
                    $commentMap,
                    $depthMap,
                    $maxDepth,
                );

                if ($parent !== null) {
                    $children = $parent->getChildren();
                    $children[] = $comment;
                    $parent->setChildren($children);
                    $comment->setParent($parent);
                } else {
                    // Fallback: add as root if no valid parent found
                    $rootComments[] = $comment;
                }
            }
        }

        return $rootComments;
    }

    /**
     * Calculate depth of a comment from the map.
     *
     * @param array<int, Comment> $commentMap
     */
    private function calculateDepthFromMap(
        Comment $comment,
        array $commentMap,
    ): int {
        $depth = 0;
        $currentParentId = $comment->parentId;

        while ($currentParentId !== null && isset($commentMap[$currentParentId])) {
            $depth++;
            $currentParentId = $commentMap[$currentParentId]->parentId;
        }

        return $depth;
    }

    /**
     * Find the effective parent for a comment respecting max depth.
     * If the natural parent would put the comment beyond max depth,
     * find an ancestor at max depth - 1 to use instead.
     *
     * @param array<int, Comment> $commentMap
     * @param array<int, int> $depthMap
     */
    private function findEffectiveParent(
        Comment $comment,
        array $commentMap,
        array $depthMap,
        int $maxDepth,
    ): ?Comment {
        if ($comment->parentId === null) {
            return null;
        }

        $naturalParent = $commentMap[$comment->parentId] ?? null;
        if ($naturalParent === null) {
            return null;
        }

        $naturalParentDepth = $depthMap[$naturalParent->id] ?? 0;

        // If natural parent is at max depth or beyond, find an ancestor at maxDepth - 1
        if ($naturalParentDepth >= $maxDepth) {
            // Walk up the tree to find the ancestor at depth maxDepth - 1
            $current = $naturalParent;
            while ($current !== null && ($depthMap[$current->id] ?? 0) >= $maxDepth) {
                if ($current->parentId === null) {
                    break;
                }
                $current = $commentMap[$current->parentId] ?? null;
            }

            return $current;
        }

        return $naturalParent;
    }
}
