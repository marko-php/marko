<?php

declare(strict_types=1);

namespace Marko\Blog\Repositories;

use Closure;
use Marko\Blog\Config\BlogConfigInterface;
use Marko\Blog\Entity\Comment;
use Marko\Blog\Enum\CommentStatus;
use Marko\Blog\Events\Comment\CommentCreated;
use Marko\Blog\Events\Comment\CommentDeleted;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Repository\Repository;

class CommentRepository extends Repository implements CommentRepositoryInterface
{
    protected const string ENTITY_CLASS = Comment::class;

    public function __construct(
        ConnectionInterface $connection,
        EntityMetadataFactory $metadataFactory,
        EntityHydrator $hydrator,
        private readonly BlogConfigInterface $blogConfig,
        ?Closure $queryBuilderFactory = null,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        parent::__construct($connection, $metadataFactory, $hydrator, $queryBuilderFactory);
    }

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
     * Get threaded comments for a post as a tree structure.
     * Returns only root-level comments with children populated.
     *
     * @return array<Comment>
     */
    public function getThreadedCommentsForPost(
        int $postId,
    ): array {
        $comments = $this->findVerifiedForPost($postId);

        return $this->buildTree($comments);
    }

    /**
     * Build a tree structure from flat list of comments.
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
    public function findByAuthorEmail(
        string $email,
    ): array {
        $sql = sprintf(
            'SELECT * FROM %s WHERE author_email = ?',
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

    /**
     * Calculate depth of a comment in the thread.
     * Returns 0 for root comments, 1 for direct replies, etc.
     */
    public function calculateDepth(
        int $commentId,
    ): int {
        $comment = $this->find($commentId);

        if ($comment === null) {
            return 0;
        }

        $depth = 0;
        $currentParentId = $comment->parentId;

        while ($currentParentId !== null) {
            $depth++;
            $parent = $this->find($currentParentId);
            $currentParentId = $parent?->parentId;
        }

        return $depth;
    }
}
