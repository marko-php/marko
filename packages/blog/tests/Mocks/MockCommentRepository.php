<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Mocks;

use Marko\Blog\Entity\Comment;
use Marko\Blog\Repositories\CommentRepositoryInterface;
use Marko\Database\Entity\Entity;
use RuntimeException;

class MockCommentRepository implements CommentRepositoryInterface
{
    public function find(
        int $id,
    ): ?Comment {
        return null;
    }

    public function findOrFail(
        int $id,
    ): Comment {
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
    ): ?Comment {
        return null;
    }

    public function findVerifiedForPost(
        int $postId,
    ): array {
        return [];
    }

    public function findPendingForPost(
        int $postId,
    ): array {
        return [];
    }

    public function getThreadedCommentsForPost(
        int $postId,
    ): array {
        return [];
    }

    public function countForPost(
        int $postId,
    ): int {
        return 0;
    }

    public function countVerifiedForPost(
        int $postId,
    ): int {
        return 0;
    }

    public function findByAuthorEmail(
        string $email,
    ): array {
        return [];
    }

    public function calculateDepth(
        int $commentId,
    ): int {
        return 0;
    }

    public function save(Entity $entity): void {}

    public function delete(Entity $entity): void {}
}
