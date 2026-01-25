<?php

declare(strict_types=1);

namespace Marko\Blog\Repositories;

use Marko\Blog\Entity\Tag;
use Marko\Blog\Exceptions\TagHasPostsException;
use Marko\Blog\Services\SlugGeneratorInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Repository\Repository;

class TagRepository extends Repository implements TagRepositoryInterface
{
    protected const string ENTITY_CLASS = Tag::class;

    public function __construct(
        ConnectionInterface $connection,
        EntityMetadataFactory $metadataFactory,
        EntityHydrator $hydrator,
        protected readonly SlugGeneratorInterface $slugGenerator,
    ) {
        parent::__construct($connection, $metadataFactory, $hydrator);
    }

    /**
     * Find a tag by its slug.
     */
    public function findBySlug(
        string $slug,
    ): ?Tag {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * Find tags by partial name match.
     *
     * @return array<Tag>
     */
    public function findByNameLike(
        string $name,
    ): array {
        $sql = sprintf(
            'SELECT * FROM %s WHERE name LIKE ?',
            $this->metadata->tableName,
        );

        $rows = $this->connection->query($sql, ["%$name%"]);

        return array_map(
            fn (array $row): Tag => $this->hydrator->hydrate(
                self::ENTITY_CLASS,
                $row,
                $this->metadata,
            ),
            $rows,
        );
    }

    /**
     * Check if a slug is unique within the tags table.
     */
    public function isSlugUnique(
        string $slug,
        ?int $excludeId = null,
    ): bool {
        if ($excludeId === null) {
            $sql = sprintf(
                'SELECT * FROM %s WHERE slug = ?',
                $this->metadata->tableName,
            );
            $rows = $this->connection->query($sql, [$slug]);
        } else {
            $sql = sprintf(
                'SELECT * FROM %s WHERE slug = ? AND id != ?',
                $this->metadata->tableName,
            );
            $rows = $this->connection->query($sql, [$slug, $excludeId]);
        }

        return count($rows) === 0;
    }

    /**
     * Save a tag entity.
     *
     * Auto-generates slug from name if not set.
     */
    public function save(
        Entity $entity,
    ): void {
        if (!$entity instanceof Tag) {
            parent::save($entity);

            return;
        }

        // Auto-generate slug if not set
        if (!isset($entity->slug) || $entity->slug === '') {
            $entity->slug = $this->slugGenerator->generate(
                $entity->name,
                fn (string $slug): bool => $this->isSlugUnique($slug, $entity->id),
            );
        }

        parent::save($entity);
    }

    /**
     * Delete a tag entity.
     *
     * @throws TagHasPostsException if the tag has associated posts
     */
    public function delete(
        Entity $entity,
    ): void {
        if (!$entity instanceof Tag) {
            parent::delete($entity);

            return;
        }

        // Check if tag has associated posts
        $postCount = $this->countAssociatedPosts($entity->id);

        if ($postCount > 0) {
            throw TagHasPostsException::cannotDelete($entity->name, $postCount);
        }

        parent::delete($entity);
    }

    /**
     * Count the number of posts associated with a tag.
     */
    private function countAssociatedPosts(
        int $tagId,
    ): int {
        $sql = 'SELECT COUNT(*) as count FROM post_tags WHERE tag_id = ?';
        $result = $this->connection->query($sql, [$tagId]);

        return (int) ($result[0]['count'] ?? 0);
    }
}
