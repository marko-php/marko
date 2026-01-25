# Task 007: Expand Post Entity

**Status**: pending
**Depends on**: 002, 003, 006
**Retry count**: 0

## Description
Create PostInterface and PostRepositoryInterface, then expand the existing Post entity to include status workflow, scheduling, summary field, and author relationship. The post slug should auto-generate from title but allow manual override.

**IMPORTANT:** The existing Post and PostRepository have NO interfaces. This task must CREATE them for extensibility.

## Context
- Related files:
  - `packages/blog/src/Entity/PostInterface.php` (NEW - create)
  - `packages/blog/src/Entity/Post.php` (existing - expand)
  - `packages/blog/src/Repositories/PostRepositoryInterface.php` (NEW - create)
  - `packages/blog/src/Repositories/PostRepository.php` (existing - expand)
- Patterns to follow: Other entity interfaces in blog package (AuthorInterface, etc.)
- Post now has foreign key to Author
- SlugGenerator (from Task 002) used for auto-generating slugs

## Requirements (Test Descriptions)
- [ ] `it creates PostInterface with all post property accessors`
- [ ] `it creates PostRepositoryInterface with all repository methods`
- [ ] `it has status field defaulting to draft`
- [ ] `it has scheduled_at nullable datetime field`
- [ ] `it has published_at nullable datetime field`
- [ ] `it has created_at timestamp`
- [ ] `it has updated_at timestamp`
- [ ] `it has summary text field`
- [ ] `it has author_id foreign key to authors table`
- [ ] `it sets published_at when status changes to published`
- [ ] `it auto-generates slug from title using SlugGenerator`
- [ ] `it allows manual slug override`
- [ ] `it ensures slug uniqueness within posts table`
- [ ] `it validates scheduled_at is set when status is scheduled`
- [ ] `it returns associated author entity`
- [ ] `it finds posts by status`
- [ ] `it finds published posts only`
- [ ] `it finds posts by author`
- [ ] `it finds scheduled posts due for publishing`
- [ ] `it counts posts by author`
- [ ] `it determines if post was modified after publishing via wasUpdatedAfterPublishing method`
- [ ] `it checks if slug is unique via isSlugUnique method`

## PostInterface

```php
interface PostInterface
{
    public function getId(): ?int;
    public function getTitle(): string;
    public function getSlug(): string;
    public function getContent(): string;
    public function getSummary(): ?string;
    public function getStatus(): PostStatus;
    public function getAuthorId(): int;
    public function getAuthor(): AuthorInterface;
    public function getScheduledAt(): ?DateTimeImmutable;
    public function getPublishedAt(): ?DateTimeImmutable;
    public function getCreatedAt(): ?DateTimeImmutable;
    public function getUpdatedAt(): ?DateTimeImmutable;
    public function wasUpdatedAfterPublishing(): bool;
    public function isPublished(): bool;
    public function isDraft(): bool;
    public function isScheduled(): bool;
}
```

## PostRepositoryInterface

```php
interface PostRepositoryInterface
{
    public function find(int $id): ?PostInterface;
    public function findBySlug(string $slug): ?PostInterface;
    public function findAll(): array;
    public function findPublished(): array;
    public function findByStatus(PostStatus $status): array;
    public function findByAuthor(int $authorId): array;
    public function findScheduledPostsDue(): array;
    public function countByAuthor(int $authorId): int;
    public function isSlugUnique(string $slug, ?int $excludeId = null): bool;
    public function save(PostInterface $post): void;
    public function delete(PostInterface $post): void;
}
```

## findScheduledPostsDue() Method

This method is critical for the `blog:publish-scheduled` CLI command (Task 045).

```php
/**
 * Find all posts with status=scheduled and scheduled_at <= now.
 * Used by the blog:publish-scheduled command.
 *
 * @return PostInterface[]
 */
public function findScheduledPostsDue(): array
{
    return $this->findBy([
        'status' => PostStatus::Scheduled,
        ['scheduled_at', '<=', new DateTimeImmutable()],
    ]);
}
```

## Acceptance Criteria
- All requirements have passing tests
- PostInterface CREATED with all property accessors and methods
- PostRepositoryInterface CREATED with all repository method signatures
- Post entity implements PostInterface, updated with new attributes
- PostRepository implements PostRepositoryInterface, updated with new query methods
- `isSlugUnique(string $slug, ?int $excludeId): bool` method for slug generation
- `findScheduledPostsDue()` method for publishing scheduled posts
- `countByAuthor(int $authorId): int` method for author deletion checks
- Existing tests still pass (update imports to use interfaces where appropriate)
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
