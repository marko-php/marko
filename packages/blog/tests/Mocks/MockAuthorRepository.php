<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Mocks;

use Marko\Blog\Entity\Author;
use Marko\Blog\Repositories\AuthorRepositoryInterface;
use Marko\Database\Entity\Entity;
use RuntimeException;

class MockAuthorRepository implements AuthorRepositoryInterface
{
    public function find(
        int $id,
    ): ?Author {
        return null;
    }

    public function findOrFail(
        int $id,
    ): Author {
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
    ): ?Author {
        return null;
    }

    public function existsBy(
        array $criteria,
    ): bool {
        return $this->findOneBy(criteria: $criteria) !== null;
    }

    public function findBySlug(
        string $slug,
    ): ?Author {
        return null;
    }

    public function findByEmail(
        string $email,
    ): ?Author {
        return null;
    }

    public function isSlugUnique(
        string $slug,
        ?int $excludeId = null,
    ): bool {
        return true;
    }

    public function save(Entity $entity): void {}

    public function delete(Entity $entity): void {}
}
