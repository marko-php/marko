# Task 002: Slug Generator Service

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create a service that generates URL-friendly slugs from titles. The service handles transliteration, special characters, and ensures uniqueness by appending numbers when needed.

## Context
- Related files: `packages/blog/src/Services/SlugGeneratorInterface.php`, `packages/blog/src/Services/SlugGenerator.php`
- Patterns to follow: Interface/implementation split per Marko conventions
- Slugs are used for posts, authors, categories, and tags

## Requirements (Test Descriptions)
- [ ] `it converts title to lowercase slug`
- [ ] `it replaces spaces with hyphens`
- [ ] `it removes special characters except hyphens`
- [ ] `it collapses multiple hyphens into single hyphen`
- [ ] `it trims hyphens from start and end`
- [ ] `it handles unicode characters by transliterating to ASCII`
- [ ] `it generates unique slug by appending number when duplicate exists`
- [ ] `it accepts custom uniqueness checker callback`

## Slug Scope (Uniqueness Boundaries)

**Slugs are unique per entity type, not globally unique.**

This allows the same slug to exist across different entity types:
- `/blog/author/john` (author with slug "john")
- `/blog/category/john` (category with slug "john")
- `/blog/tag/john` (tag with slug "john")
- `/blog/john` (post with slug "john")

All four can coexist because they're in different URL namespaces.

**Uniqueness is enforced at the repository level:**
- `PostRepository::isSlugUnique(string $slug, ?int $excludeId): bool`
- `AuthorRepository::isSlugUnique(string $slug, ?int $excludeId): bool`
- `CategoryRepository::isSlugUnique(string $slug, ?int $excludeId): bool`
- `TagRepository::isSlugUnique(string $slug, ?int $excludeId): bool`

The SlugGenerator accepts a uniqueness checker callback, allowing each repository to provide its own scope:

```php
$slug = $slugGenerator->generate(
    title: 'My Post Title',
    uniquenessChecker: fn(string $slug) => $postRepository->isSlugUnique($slug),
);
```

## Acceptance Criteria
- All requirements have passing tests
- SlugGeneratorInterface defined with generate() method
- SlugGenerator implements the interface
- Uniqueness checker callback allows per-entity-type scope
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
