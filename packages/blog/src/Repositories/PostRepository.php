<?php

declare(strict_types=1);

namespace Marko\Blog\Repositories;

use Closure;
use DateTimeImmutable;
use Marko\Blog\Entity\Category;
use Marko\Blog\Entity\Post;
use Marko\Blog\Entity\Tag;
use Marko\Blog\Enum\PostStatus;
use Marko\Blog\Events\Post\PostCreated;
use Marko\Blog\Events\Post\PostDeleted;
use Marko\Blog\Events\Post\PostPublished;
use Marko\Blog\Events\Post\PostScheduled;
use Marko\Blog\Events\Post\PostUpdated;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Repository\Repository;

/**
 * @extends Repository<Post>
 */
class PostRepository extends Repository implements PostRepositoryInterface
{
    protected const string ENTITY_CLASS = Post::class;

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
     * Save a post, dispatching appropriate events.
     */
    public function save(
        Entity $entity,
    ): void {
        if (!$entity instanceof Post) {
            parent::save($entity);

            return;
        }

        $isNew = $entity->id === null;
        $originalValues = $this->hydrator->getOriginalValues($entity);
        $previousStatus = $originalValues['status'] ?? null;

        parent::save($entity);

        $this->dispatchSaveEvent($entity, $isNew, $previousStatus);
    }

    private function dispatchSaveEvent(
        Post $post,
        bool $isNew,
        ?PostStatus $previousStatus,
    ): void {
        if ($this->eventDispatcher === null) {
            return;
        }

        if ($isNew) {
            $this->eventDispatcher->dispatch(new PostCreated(
                post: $post,
            ));
        } else {
            $this->eventDispatcher->dispatch(new PostUpdated(
                post: $post,
            ));

            // Check for status change events
            $this->dispatchStatusChangeEvent($post, $previousStatus);
        }
    }

    /**
     * Delete a post, dispatching appropriate events.
     */
    public function delete(
        Entity $entity,
    ): void {
        if (!$entity instanceof Post) {
            parent::delete($entity);

            return;
        }

        parent::delete($entity);

        $this->eventDispatcher?->dispatch(new PostDeleted(
            post: $entity,
        ));
    }

    private function dispatchStatusChangeEvent(
        Post $post,
        ?PostStatus $previousStatus,
    ): void {
        if ($this->eventDispatcher === null || $previousStatus === null) {
            return;
        }

        $currentStatus = $post->getStatus();

        if ($previousStatus === $currentStatus) {
            return;
        }

        if ($currentStatus === PostStatus::Published) {
            $this->eventDispatcher->dispatch(new PostPublished(
                post: $post,
                previousStatus: $previousStatus,
            ));
        } elseif ($currentStatus === PostStatus::Scheduled) {
            $this->eventDispatcher->dispatch(new PostScheduled(
                post: $post,
                previousStatus: $previousStatus,
            ));
        }
    }

    /**
     * Find a post by its slug.
     */
    public function findBySlug(
        string $slug,
    ): ?Post {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * Find all published posts.
     *
     * @return array<Post>
     */
    public function findPublished(): array
    {
        return $this->findByStatus(PostStatus::Published);
    }

    /**
     * Find published posts with pagination, ordered by published_at DESC.
     *
     * @return array<Post>
     */
    public function findPublishedPaginated(
        int $limit,
        int $offset,
    ): array {
        // LIMIT and OFFSET must be interpolated directly as integers
        // because PDO binds all values as strings by default
        $sql = sprintf(
            'SELECT * FROM %s WHERE status = ? ORDER BY published_at DESC LIMIT %d OFFSET %d',
            $this->metadata->tableName,
            $limit,
            $offset,
        );

        $rows = $this->connection->query($sql, [
            PostStatus::Published->value,
        ]);

        return array_map(
            fn (array $row): Post => $this->hydrator->hydrate(
                static::ENTITY_CLASS,
                $row,
                $this->metadata,
            ),
            $rows,
        );
    }

    /**
     * Count all published posts.
     */
    public function countPublished(): int
    {
        $sql = sprintf(
            'SELECT COUNT(*) as count FROM %s WHERE status = ?',
            $this->metadata->tableName,
        );

        $result = $this->connection->query($sql, [
            PostStatus::Published->value,
        ]);

        return (int) ($result[0]['count'] ?? 0);
    }

    /**
     * Find posts by status.
     *
     * @return array<Post>
     */
    public function findByStatus(
        PostStatus $status,
    ): array {
        $sql = sprintf(
            'SELECT * FROM %s WHERE status = ?',
            $this->metadata->tableName,
        );

        $rows = $this->connection->query($sql, [$status->value]);

        return array_map(
            fn (array $row): Post => $this->hydrator->hydrate(
                static::ENTITY_CLASS,
                $row,
                $this->metadata,
            ),
            $rows,
        );
    }

    /**
     * Find posts by author.
     *
     * @return array<Post>
     */
    public function findByAuthor(
        int $authorId,
    ): array {
        $sql = sprintf(
            'SELECT * FROM %s WHERE author_id = ?',
            $this->metadata->tableName,
        );

        $rows = $this->connection->query($sql, [$authorId]);

        return array_map(
            fn (array $row): Post => $this->hydrator->hydrate(
                static::ENTITY_CLASS,
                $row,
                $this->metadata,
            ),
            $rows,
        );
    }

    /**
     * Find scheduled posts that are due for publishing.
     *
     * @return array<Post>
     */
    public function findScheduledPostsDue(): array
    {
        $now = new DateTimeImmutable();

        $sql = sprintf(
            'SELECT * FROM %s WHERE status = ? AND scheduled_at <= ?',
            $this->metadata->tableName,
        );

        $rows = $this->connection->query($sql, [
            PostStatus::Scheduled->value,
            $now->format('Y-m-d H:i:s'),
        ]);

        return array_map(
            fn (array $row): Post => $this->hydrator->hydrate(
                static::ENTITY_CLASS,
                $row,
                $this->metadata,
            ),
            $rows,
        );
    }

    /**
     * Count posts by author.
     */
    public function countByAuthor(
        int $authorId,
    ): int {
        $sql = sprintf(
            'SELECT COUNT(*) as count FROM %s WHERE author_id = ?',
            $this->metadata->tableName,
        );

        $result = $this->connection->query($sql, [$authorId]);

        return (int) ($result[0]['count'] ?? 0);
    }

    /**
     * Find published posts by author with pagination.
     *
     * @return array<Post>
     */
    public function findPublishedByAuthor(
        int $authorId,
        int $limit,
        int $offset,
    ): array {
        $sql = sprintf(
            'SELECT * FROM %s WHERE author_id = ? AND status = ? ORDER BY published_at DESC LIMIT %d OFFSET %d',
            $this->metadata->tableName,
            $limit,
            $offset,
        );

        $rows = $this->connection->query($sql, [
            $authorId,
            PostStatus::Published->value,
        ]);

        return array_map(
            fn (array $row): Post => $this->hydrator->hydrate(
                static::ENTITY_CLASS,
                $row,
                $this->metadata,
            ),
            $rows,
        );
    }

    /**
     * Count published posts by author.
     */
    public function countPublishedByAuthor(
        int $authorId,
    ): int {
        $sql = sprintf(
            'SELECT COUNT(*) as count FROM %s WHERE author_id = ? AND status = ?',
            $this->metadata->tableName,
        );

        $result = $this->connection->query($sql, [
            $authorId,
            PostStatus::Published->value,
        ]);

        return (int) ($result[0]['count'] ?? 0);
    }

    /**
     * Check if a slug is unique within the posts table.
     *
     * @param string $slug The slug to check
     * @param int|null $excludeId Optional post ID to exclude (for updates)
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
     * Find published posts by tag with pagination.
     *
     * @return array<Post>
     */
    public function findPublishedByTag(
        int $tagId,
        int $limit,
        int $offset,
    ): array {
        $sql = sprintf(
            'SELECT p.* FROM %s p
            INNER JOIN post_tags pt ON p.id = pt.post_id
            WHERE pt.tag_id = ? AND p.status = ?
            ORDER BY p.published_at DESC
            LIMIT %d OFFSET %d',
            $this->metadata->tableName,
            $limit,
            $offset,
        );

        $rows = $this->connection->query($sql, [
            $tagId,
            PostStatus::Published->value,
        ]);

        return array_map(
            fn (array $row): Post => $this->hydrator->hydrate(
                static::ENTITY_CLASS,
                $row,
                $this->metadata,
            ),
            $rows,
        );
    }

    /**
     * Count published posts by tag.
     */
    public function countPublishedByTag(
        int $tagId,
    ): int {
        $sql = sprintf(
            'SELECT COUNT(*) as count FROM %s p
            INNER JOIN post_tags pt ON p.id = pt.post_id
            WHERE pt.tag_id = ? AND p.status = ?',
            $this->metadata->tableName,
        );

        $result = $this->connection->query($sql, [
            $tagId,
            PostStatus::Published->value,
        ]);

        return (int) ($result[0]['count'] ?? 0);
    }

    /**
     * Find published posts by category with pagination.
     *
     * @return array<Post>
     */
    public function findPublishedByCategory(
        int $categoryId,
        int $limit,
        int $offset,
    ): array {
        $sql = sprintf(
            'SELECT p.* FROM %s p
            INNER JOIN post_categories pc ON p.id = pc.post_id
            WHERE pc.category_id = ? AND p.status = ?
            ORDER BY p.published_at DESC
            LIMIT %d OFFSET %d',
            $this->metadata->tableName,
            $limit,
            $offset,
        );

        $rows = $this->connection->query($sql, [
            $categoryId,
            PostStatus::Published->value,
        ]);

        return array_map(
            fn (array $row): Post => $this->hydrator->hydrate(
                static::ENTITY_CLASS,
                $row,
                $this->metadata,
            ),
            $rows,
        );
    }

    /**
     * Count published posts by category.
     */
    public function countPublishedByCategory(
        int $categoryId,
    ): int {
        $sql = sprintf(
            'SELECT COUNT(*) as count FROM %s p
            INNER JOIN post_categories pc ON p.id = pc.post_id
            WHERE pc.category_id = ? AND p.status = ?',
            $this->metadata->tableName,
        );

        $result = $this->connection->query($sql, [
            $categoryId,
            PostStatus::Published->value,
        ]);

        return (int) ($result[0]['count'] ?? 0);
    }

    /**
     * Find published posts by multiple categories with pagination.
     *
     * @param array<int> $categoryIds
     * @return array<Post>
     */
    public function findPublishedByCategories(
        array $categoryIds,
        int $limit,
        int $offset,
    ): array {
        if ($categoryIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));

        $sql = sprintf(
            'SELECT DISTINCT p.* FROM %s p
            INNER JOIN post_categories pc ON p.id = pc.post_id
            WHERE pc.category_id IN (%s) AND p.status = ?
            ORDER BY p.published_at DESC
            LIMIT %d OFFSET %d',
            $this->metadata->tableName,
            $placeholders,
            $limit,
            $offset,
        );

        $params = [...$categoryIds, PostStatus::Published->value];

        $rows = $this->connection->query($sql, $params);

        return array_map(
            fn (array $row): Post => $this->hydrator->hydrate(
                static::ENTITY_CLASS,
                $row,
                $this->metadata,
            ),
            $rows,
        );
    }

    /**
     * Count published posts by multiple categories.
     *
     * @param array<int> $categoryIds
     */
    public function countPublishedByCategories(
        array $categoryIds,
    ): int {
        if ($categoryIds === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));

        $sql = sprintf(
            'SELECT COUNT(DISTINCT p.id) as count FROM %s p
            INNER JOIN post_categories pc ON p.id = pc.post_id
            WHERE pc.category_id IN (%s) AND p.status = ?',
            $this->metadata->tableName,
            $placeholders,
        );

        $params = [...$categoryIds, PostStatus::Published->value];

        $result = $this->connection->query($sql, $params);

        return (int) ($result[0]['count'] ?? 0);
    }

    /**
     * Attach a category to a post.
     */
    public function attachCategory(
        int $postId,
        int $categoryId,
    ): void {
        $sql = 'INSERT INTO post_categories (post_id, category_id) VALUES (?, ?)';

        $this->connection->execute($sql, [$postId, $categoryId]);
    }

    /**
     * Detach a category from a post.
     */
    public function detachCategory(
        int $postId,
        int $categoryId,
    ): void {
        $sql = 'DELETE FROM post_categories WHERE post_id = ? AND category_id = ?';

        $this->connection->execute($sql, [$postId, $categoryId]);
    }

    /**
     * Attach a tag to a post.
     */
    public function attachTag(
        int $postId,
        int $tagId,
    ): void {
        $sql = 'INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)';

        $this->connection->execute($sql, [$postId, $tagId]);
    }

    /**
     * Detach a tag from a post.
     */
    public function detachTag(
        int $postId,
        int $tagId,
    ): void {
        $sql = 'DELETE FROM post_tags WHERE post_id = ? AND tag_id = ?';

        $this->connection->execute($sql, [$postId, $tagId]);
    }

    /**
     * Get all categories for a post.
     *
     * @return array<Category>
     */
    public function getCategoriesForPost(
        int $postId,
    ): array {
        $sql = 'SELECT c.* FROM categories c
            INNER JOIN post_categories pc ON c.id = pc.category_id
            WHERE pc.post_id = ?';

        $rows = $this->connection->query($sql, [$postId]);

        $categoryMetadata = $this->metadataFactory->parse(Category::class);

        return array_map(
            fn (array $row): Category => $this->hydrator->hydrate(
                Category::class,
                $row,
                $categoryMetadata,
            ),
            $rows,
        );
    }

    /**
     * Get all tags for a post.
     *
     * @return array<Tag>
     */
    public function getTagsForPost(
        int $postId,
    ): array {
        $sql = 'SELECT t.* FROM tags t
            INNER JOIN post_tags pt ON t.id = pt.tag_id
            WHERE pt.post_id = ?';

        $rows = $this->connection->query($sql, [$postId]);

        $tagMetadata = $this->metadataFactory->parse(Tag::class);

        return array_map(
            fn (array $row): Tag => $this->hydrator->hydrate(
                Tag::class,
                $row,
                $tagMetadata,
            ),
            $rows,
        );
    }

    /**
     * Sync categories for a post, replacing all existing.
     *
     * @param array<int> $categoryIds
     */
    public function syncCategories(
        int $postId,
        array $categoryIds,
    ): void {
        // Remove all existing categories for this post
        $sql = 'DELETE FROM post_categories WHERE post_id = ?';
        $this->connection->execute($sql, [$postId]);

        // Attach the new categories
        foreach ($categoryIds as $categoryId) {
            $this->attachCategory($postId, $categoryId);
        }
    }

    /**
     * Sync tags for a post, replacing all existing.
     *
     * @param array<int> $tagIds
     */
    public function syncTags(
        int $postId,
        array $tagIds,
    ): void {
        // Remove all existing tags for this post
        $sql = 'DELETE FROM post_tags WHERE post_id = ?';
        $this->connection->execute($sql, [$postId]);

        // Attach the new tags
        foreach ($tagIds as $tagId) {
            $this->attachTag($postId, $tagId);
        }
    }
}
