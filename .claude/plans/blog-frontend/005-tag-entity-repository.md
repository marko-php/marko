# Task 005: Tag Entity and Repository

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
Create the Tag entity for flat (non-hierarchical) post tagging. Tags are simpler than categories - just a name and slug with no parent relationship.

## Context
- Related files: `packages/blog/src/Entity/Tag.php`, `packages/blog/src/Repositories/TagRepository.php`
- Patterns to follow: Similar to Category but without hierarchy
- Tags are typically more granular than categories

## Requirements (Test Descriptions)
- [ ] `it creates tag with name and slug`
- [ ] `it requires name field`
- [ ] `it auto-generates slug from name using SlugGenerator`
- [ ] `it allows manual slug override`
- [ ] `it ensures slug uniqueness within tags table`
- [ ] `it finds tag by id`
- [ ] `it finds tag by slug`
- [ ] `it returns all tags`
- [ ] `it finds tags by partial name match`
- [ ] `it has created_at timestamp`
- [ ] `it has updated_at timestamp`
- [ ] `it checks if slug is unique via isSlugUnique method`
- [ ] `it prevents deletion when tag has posts`

## Deletion Behavior

**Tags cannot be deleted while they have associated posts.**

When attempting to delete a tag:
1. Repository checks if tag has any posts (via pivot table)
2. If posts exist, throws `TagHasPostsException` with count
3. User must remove tag from posts first

This prevents orphaned relationships and enforces data integrity.

```php
// TagRepository
public function delete(TagInterface $tag): void
{
    $postCount = $this->countPosts($tag->getId());
    if ($postCount > 0) {
        throw new TagHasPostsException($tag, $postCount);
    }
    // proceed with deletion
}
```

## TagInterface

```php
interface TagInterface
{
    public function getId(): ?int;
    public function getName(): string;
    public function getSlug(): string;
    public function getCreatedAt(): ?DateTimeImmutable;
    public function getUpdatedAt(): ?DateTimeImmutable;
}
```

## Acceptance Criteria
- All requirements have passing tests
- TagInterface defined for extensibility
- Tag entity with proper database attributes
- TagRepositoryInterface and TagRepository created
- `isSlugUnique(string $slug, ?int $excludeId): bool` method for slug generation
- Deletion prevented when posts exist
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
