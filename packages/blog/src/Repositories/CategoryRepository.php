<?php

declare(strict_types=1);

namespace Marko\Blog\Repositories;

use Closure;
use Marko\Blog\Entity\Category;
use Marko\Blog\Entity\CategoryInterface;
use Marko\Blog\Events\Category\CategoryCreated;
use Marko\Blog\Events\Category\CategoryDeleted;
use Marko\Blog\Events\Category\CategoryUpdated;
use Marko\Blog\Services\SlugGeneratorInterface;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Database\Repository\Repository;

class CategoryRepository extends Repository implements CategoryRepositoryInterface
{
    protected const string ENTITY_CLASS = Category::class;

    public function __construct(
        ConnectionInterface $connection,
        EntityMetadataFactory $metadataFactory,
        EntityHydrator $hydrator,
        private readonly SlugGeneratorInterface $slugGenerator,
        ?Closure $queryBuilderFactory = null,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        parent::__construct($connection, $metadataFactory, $hydrator, $queryBuilderFactory);
    }

    /**
     * Find a category by its slug.
     */
    public function findBySlug(
        string $slug,
    ): ?Category {
        $result = $this->findOneBy(['slug' => $slug]);

        return $result instanceof Category ? $result : null;
    }

    /**
     * Check if a slug is unique within the categories table.
     */
    public function isSlugUnique(
        string $slug,
        ?int $excludeId = null,
    ): bool {
        if ($excludeId !== null) {
            $sql = 'SELECT COUNT(*) as count FROM categories WHERE slug = ? AND id != ?';
            $result = $this->connection->query($sql, [$slug, $excludeId]);
        } else {
            $sql = 'SELECT COUNT(*) as count FROM categories WHERE slug = ?';
            $result = $this->connection->query($sql, [$slug]);
        }

        return (int) ($result[0]['count'] ?? 0) === 0;
    }

    /**
     * Find all child categories of a parent.
     *
     * @return array<Category>
     */
    public function findChildren(
        Category $parent,
    ): array {
        $results = $this->findBy(['parentId' => $parent->id]);

        return array_map(
            function (Entity $entity): Category {
                if ($entity instanceof Category) {
                    return $entity;
                }
                throw new RepositoryException('Invalid entity type');
            },
            $results,
        );
    }

    /**
     * Get the full path from root to the given category.
     *
     * @return array<Category>
     */
    public function getPath(
        Category $category,
    ): array {
        $path = [$category];
        $current = $category;

        while ($current->parentId !== null) {
            $parent = $this->find($current->parentId);
            if ($parent instanceof Category) {
                array_unshift($path, $parent);
                $current = $parent;
            } else {
                break;
            }
        }

        return $path;
    }

    /**
     * Find all root categories (categories with no parent).
     *
     * @return array<Category>
     */
    public function findRoots(): array
    {
        $sql = 'SELECT * FROM categories WHERE parent_id IS NULL';
        $rows = $this->connection->query($sql);

        return array_map(
            fn (array $row): Category => $this->hydrator->hydrate(
                Category::class,
                $row,
                $this->metadata,
            ),
            $rows,
        );
    }

    /**
     * Save a category, auto-generating slug if not set.
     */
    public function save(
        Entity $entity,
    ): void {
        if (!$entity instanceof Category) {
            throw RepositoryException::invalidEntityType(
                self::class,
                Category::class,
                $entity::class,
            );
        }

        // Auto-generate slug if not set
        if (!isset($entity->slug) || $entity->slug === '') {
            $entity->slug = $this->slugGenerator->generate(
                $entity->name,
                fn (string $slug): bool => $this->isSlugUnique($slug, $entity->id),
            );
        }

        $isNew = $entity->id === null;

        parent::save($entity);

        $this->dispatchSaveEvent($entity, $isNew);
    }

    private function dispatchSaveEvent(
        Category $category,
        bool $isNew,
    ): void {
        if ($this->eventDispatcher === null) {
            return;
        }

        $parent = $this->resolveParent($category);

        if ($isNew) {
            $this->eventDispatcher->dispatch(new CategoryCreated(
                category: $category,
                parent: $parent,
            ));
        } else {
            $this->eventDispatcher->dispatch(new CategoryUpdated(
                category: $category,
                parent: $parent,
            ));
        }
    }

    private function resolveParent(
        Category $category,
    ): ?CategoryInterface {
        if ($category->parentId === null) {
            return null;
        }

        $parent = $this->find($category->parentId);

        return $parent instanceof CategoryInterface ? $parent : null;
    }

    /**
     * Delete a category, preventing deletion if it has posts or children.
     */
    public function delete(
        Entity $entity,
    ): void {
        if (!$entity instanceof Category) {
            throw RepositoryException::invalidEntityType(
                self::class,
                Category::class,
                $entity::class,
            );
        }

        // Check for associated posts
        $sql = 'SELECT COUNT(*) as count FROM post_categories WHERE category_id = ?';
        $result = $this->connection->query($sql, [$entity->id]);
        $postCount = (int) ($result[0]['count'] ?? 0);

        if ($postCount > 0) {
            throw new RepositoryException('Cannot delete category with associated posts');
        }

        // Check for child categories
        $sql = 'SELECT COUNT(*) as count FROM categories WHERE parent_id = ?';
        $result = $this->connection->query($sql, [$entity->id]);
        $childCount = (int) ($result[0]['count'] ?? 0);

        if ($childCount > 0) {
            throw new RepositoryException('Cannot delete category with child categories');
        }

        $parent = $this->resolveParent($entity);

        parent::delete($entity);

        $this->dispatchDeleteEvent($entity, $parent);
    }

    private function dispatchDeleteEvent(
        Category $category,
        ?CategoryInterface $parent,
    ): void {
        $this->eventDispatcher?->dispatch(new CategoryDeleted(
            category: $category,
            parent: $parent,
        ));
    }
}
