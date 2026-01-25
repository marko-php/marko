# Task 003: Author Entity and Repository

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
Create the Author entity representing blog post authors with name, email, bio, and auto-generated slug. Authors are separate from system users to keep the blog module self-contained.

## Context
- Related files: `packages/blog/src/Entity/Author.php`, `packages/blog/src/Repositories/AuthorRepository.php`
- Patterns to follow: Existing `Post` entity and `PostRepository` in the blog package
- Uses database attributes: `#[Table]`, `#[Column]`, etc.

## Requirements (Test Descriptions)
- [ ] `it creates author with name email and bio`
- [ ] `it requires name field`
- [ ] `it requires email field with valid format`
- [ ] `it auto-generates slug from name using SlugGenerator`
- [ ] `it allows manual slug override`
- [ ] `it ensures slug uniqueness within authors table`
- [ ] `it has created_at timestamp`
- [ ] `it has updated_at timestamp`
- [ ] `it finds author by id`
- [ ] `it finds author by slug`
- [ ] `it finds author by email`
- [ ] `it returns all authors`
- [ ] `it checks if slug is unique via isSlugUnique method`
- [ ] `it prevents deletion when author has posts`

## Deletion Behavior

**Authors cannot be deleted while they have associated posts.**

When attempting to delete an author:
1. Repository checks if author has any posts (regardless of status)
2. If posts exist, throws `AuthorHasPostsException` with count
3. User must reassign or delete posts first

This prevents orphaned posts and enforces data integrity at the application level.

```php
// AuthorRepository
public function delete(AuthorInterface $author): void
{
    $postCount = $this->postRepository->countByAuthor($author->getId());
    if ($postCount > 0) {
        throw new AuthorHasPostsException($author, $postCount);
    }
    // proceed with deletion
}
```

## AuthorInterface

```php
interface AuthorInterface
{
    public function getId(): ?int;
    public function getName(): string;
    public function getEmail(): string;
    public function getBio(): ?string;
    public function getSlug(): string;
    public function getCreatedAt(): ?DateTimeImmutable;
    public function getUpdatedAt(): ?DateTimeImmutable;
}
```

## Acceptance Criteria
- All requirements have passing tests
- AuthorInterface defined for extensibility
- Author entity with proper database attributes
- AuthorRepositoryInterface and AuthorRepository created
- `isSlugUnique(string $slug, ?int $excludeId): bool` method for slug generation
- Deletion prevented when posts exist
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
