<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Mocks;

use Marko\Blog\Entity\Post;
use Marko\Blog\Enum\PostStatus;
use Marko\Blog\Repositories\PostRepositoryInterface;
use Marko\Database\Entity\Entity;
use RuntimeException;

class MockPostRepository implements PostRepositoryInterface
{
    public function find(
        int $id,
    ): ?Post {
        return null;
    }

    public function findOrFail(
        int $id,
    ): Post {
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
    ): ?Post {
        return null;
    }

    public function findBySlug(
        string $slug,
    ): ?Post {
        return null;
    }

    public function findPublished(): array
    {
        return [];
    }

    public function findPublishedPaginated(
        int $limit,
        int $offset,
    ): array {
        return [];
    }

    public function countPublished(): int
    {
        return 0;
    }

    public function findByStatus(
        PostStatus $status,
    ): array {
        return [];
    }

    public function findByAuthor(
        int $authorId,
    ): array {
        return [];
    }

    public function findScheduledPostsDue(): array
    {
        return [];
    }

    public function countByAuthor(
        int $authorId,
    ): int {
        return 0;
    }

    public function findPublishedByAuthor(
        int $authorId,
        int $limit,
        int $offset,
    ): array {
        return [];
    }

    public function countPublishedByAuthor(
        int $authorId,
    ): int {
        return 0;
    }

    public function isSlugUnique(
        string $slug,
        ?int $excludeId = null,
    ): bool {
        return true;
    }

    public function findPublishedByTag(
        int $tagId,
        int $limit,
        int $offset,
    ): array {
        return [];
    }

    public function countPublishedByTag(
        int $tagId,
    ): int {
        return 0;
    }

    public function findPublishedByCategory(
        int $categoryId,
        int $limit,
        int $offset,
    ): array {
        return [];
    }

    public function countPublishedByCategory(
        int $categoryId,
    ): int {
        return 0;
    }

    public function findPublishedByCategories(
        array $categoryIds,
        int $limit,
        int $offset,
    ): array {
        return [];
    }

    public function countPublishedByCategories(
        array $categoryIds,
    ): int {
        return 0;
    }

    public function attachCategory(
        int $postId,
        int $categoryId,
    ): void {}

    public function detachCategory(
        int $postId,
        int $categoryId,
    ): void {}

    public function attachTag(
        int $postId,
        int $tagId,
    ): void {}

    public function detachTag(
        int $postId,
        int $tagId,
    ): void {}

    public function getCategoriesForPost(
        int $postId,
    ): array {
        return [];
    }

    public function getTagsForPost(
        int $postId,
    ): array {
        return [];
    }

    public function syncCategories(
        int $postId,
        array $categoryIds,
    ): void {}

    public function syncTags(
        int $postId,
        array $tagIds,
    ): void {}

    public function save(Entity $entity): void {}

    public function delete(Entity $entity): void {}
}
