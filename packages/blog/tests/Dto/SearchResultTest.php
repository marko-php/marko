<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Dto;

use DateTimeImmutable;
use Marko\Blog\Dto\SearchResult;
use Marko\Blog\Entity\AuthorInterface;
use Marko\Blog\Entity\PostInterface;
use Marko\Blog\Enum\PostStatus;
use ReflectionClass;
use RuntimeException;

/**
 * Creates a minimal mock PostInterface for testing.
 */
function createMockPostForDto(
    int $id = 1,
    string $title = 'Test Post',
): PostInterface {
    return new class ($id, $title) implements PostInterface
    {
        public function __construct(
            private int $id,
            private string $title,
        ) {}

        public function getId(): ?int
        {
            return $this->id;
        }

        public function getTitle(): string
        {
            return $this->title;
        }

        public function getSlug(): string
        {
            return 'test-post';
        }

        public function getContent(): string
        {
            return 'Content';
        }

        public function getSummary(): ?string
        {
            return null;
        }

        public function getStatus(): PostStatus
        {
            return PostStatus::Published;
        }

        public function getAuthorId(): int
        {
            return 1;
        }

        public function getAuthor(): AuthorInterface
        {
            throw new RuntimeException('Not implemented');
        }

        public function getScheduledAt(): ?DateTimeImmutable
        {
            return null;
        }

        public function getPublishedAt(): ?DateTimeImmutable
        {
            return null;
        }

        public function getCreatedAt(): ?DateTimeImmutable
        {
            return null;
        }

        public function getUpdatedAt(): ?DateTimeImmutable
        {
            return null;
        }

        public function wasUpdatedAfterPublishing(): bool
        {
            return false;
        }

        public function isPublished(): bool
        {
            return true;
        }

        public function isDraft(): bool
        {
            return false;
        }

        public function isScheduled(): bool
        {
            return false;
        }
    };
}

it('creates search result with post score and matched fields', function (): void {
    $post = createMockPostForDto(1, 'PHP Tutorial');

    $result = new SearchResult($post, 13.0, ['title', 'summary']);

    expect($result->post)->toBe($post)
        ->and($result->score)->toBe(13.0)
        ->and($result->matchedFields)->toBe(['title', 'summary']);
});

it('detects title match via matchedInTitle method', function (): void {
    $post = createMockPostForDto();

    $withTitle = new SearchResult($post, 10.0, ['title']);
    $withoutTitle = new SearchResult($post, 3.0, ['summary']);

    expect($withTitle->matchedInTitle())->toBeTrue()
        ->and($withoutTitle->matchedInTitle())->toBeFalse();
});

it('detects summary match via matchedInSummary method', function (): void {
    $post = createMockPostForDto();

    $withSummary = new SearchResult($post, 3.0, ['summary']);
    $withoutSummary = new SearchResult($post, 10.0, ['title']);

    expect($withSummary->matchedInSummary())->toBeTrue()
        ->and($withoutSummary->matchedInSummary())->toBeFalse();
});

it('is immutable readonly class', function (): void {
    $reflection = new ReflectionClass(SearchResult::class);

    expect($reflection->isReadOnly())->toBeTrue();
});
