<?php

declare(strict_types=1);

namespace Marko\Blog\Repositories;

use DateTimeImmutable;
use Marko\Blog\Entity\Post;
use Marko\Blog\Enum\PostStatus;
use Marko\Database\Repository\Repository;

class PostRepository extends Repository implements PostRepositoryInterface
{
    protected const string ENTITY_CLASS = Post::class;

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
}
