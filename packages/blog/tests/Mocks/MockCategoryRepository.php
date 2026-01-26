<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Mocks;

use Marko\Blog\Entity\Category;
use Marko\Blog\Repositories\CategoryRepositoryInterface;
use Marko\Database\Entity\Entity;
use RuntimeException;

class MockCategoryRepository implements CategoryRepositoryInterface
{
    public function find(
        int $id,
    ): ?Category {
        return null;
    }

    public function findOrFail(
        int $id,
    ): Category {
        throw new RuntimeException('Not found');
    }

    public function findAll(): array
    {
        return [];
    }

    public function findBy(
        array $criteria,
    ): array {
        return [];
    }

    public function findOneBy(
        array $criteria,
    ): ?Category {
        return null;
    }

    public function findBySlug(
        string $slug,
    ): ?Category {
        return null;
    }

    public function isSlugUnique(
        string $slug,
        ?int $excludeId = null,
    ): bool {
        return true;
    }

    public function findChildren(
        Category $parent,
    ): array {
        return [];
    }

    public function getPath(
        Category $category,
    ): array {
        return [$category];
    }

    public function findRoots(): array
    {
        return [];
    }

    public function getPostsForCategory(
        int $categoryId,
    ): array {
        return [];
    }

    public function getDescendantIds(
        int $categoryId,
    ): array {
        return [];
    }

    public function save(Entity $entity): void {}

    public function delete(Entity $entity): void {}
}
