<?php

declare(strict_types=1);

namespace Marko\Blog\Repositories;

use DateTimeImmutable;
use Marko\Blog\Entity\Post;
use Marko\Blog\Entity\Tag;
use Marko\Blog\Events\Tag\TagCreated;
use Marko\Blog\Events\Tag\TagDeleted;
use Marko\Blog\Events\Tag\TagUpdated;
use Marko\Blog\Exceptions\TagHasPostsException;
use Marko\Blog\Services\SlugGeneratorInterface;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Database\Repository\Repository;

/**
 * @extends Repository<Tag>
 */
class TagRepository extends Repository implements TagRepositoryInterface
{
    protected const string ENTITY_CLASS = Tag::class;

    public function __construct(
        ConnectionInterface $connection,
        EntityMetadataFactory $metadataFactory,
        EntityHydrator $hydrator,
        private readonly SlugGeneratorInterface $slugGenerator,
        ?QueryBuilderFactoryInterface $queryBuilderFactory = null,
        ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        parent::__construct($connection, $metadataFactory, $hydrator, $queryBuilderFactory, $eventDispatcher);
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
        return $this->isColumnUnique('slug', $slug, $excludeId);
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

        $isNew = $entity->id === null;

        // Auto-generate slug if not set
        if (!isset($entity->slug) || $entity->slug === '') {
            $entity->slug = $this->slugGenerator->generate(
                $entity->name,
                fn (string $slug): bool => $this->isSlugUnique($slug, $entity->id),
            );
        }

        parent::save($entity);

        $this->dispatchTagEvent($entity, $isNew);
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

        $this->eventDispatcher?->dispatch(
            new TagDeleted($entity, new DateTimeImmutable()),
        );
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

    /**
     * Get all posts for a tag.
     *
     * @return array<Post>
     */
    public function getPostsForTag(
        int $tagId,
    ): array {
        $sql = 'SELECT p.* FROM posts p
            INNER JOIN post_tags pt ON p.id = pt.post_id
            WHERE pt.tag_id = ?';

        $rows = $this->connection->query($sql, [$tagId]);

        $postMetadata = $this->metadataFactory->parse(Post::class);

        return array_map(
            fn (array $row): Post => $this->hydrator->hydrate(
                Post::class,
                $row,
                $postMetadata,
            ),
            $rows,
        );
    }

    /**
     * Dispatch the appropriate tag event based on whether the tag is new or existing.
     */
    private function dispatchTagEvent(
        Tag $tag,
        bool $isNew,
    ): void {
        if ($this->eventDispatcher === null) {
            return;
        }

        $timestamp = new DateTimeImmutable();

        if ($isNew) {
            $this->eventDispatcher->dispatch(new TagCreated($tag, $timestamp));
        } else {
            $this->eventDispatcher->dispatch(new TagUpdated($tag, $timestamp));
        }
    }
}
