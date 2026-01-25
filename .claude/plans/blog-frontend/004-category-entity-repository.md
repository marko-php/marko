# Task 004: Category Entity and Repository

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
Create the Category entity for hierarchical post categorization. Categories can have parent categories, enabling nested taxonomies like "Tech > Programming > PHP".

## Context
- Related files: `packages/blog/src/Entity/Category.php`, `packages/blog/src/Repositories/CategoryRepository.php`
- Patterns to follow: Existing entity/repository patterns in blog package
- Self-referential parent_id for hierarchy

## Requirements (Test Descriptions)
- [ ] `it creates category with name and slug`
- [ ] `it requires name field`
- [ ] `it auto-generates slug from name using SlugGenerator`
- [ ] `it allows manual slug override`
- [ ] `it ensures slug uniqueness within categories table`
- [ ] `it allows optional parent category`
- [ ] `it finds category by id`
- [ ] `it finds category by slug`
- [ ] `it returns child categories for a parent`
- [ ] `it returns full path from root to category`
- [ ] `it returns root categories with no parent`
- [ ] `it has created_at timestamp`
- [ ] `it has updated_at timestamp`
- [ ] `it checks if slug is unique via isSlugUnique method`
- [ ] `it prevents deletion when category has posts`
- [ ] `it prevents deletion when category has children`

## Deletion Behavior

**Categories cannot be deleted while they have associated posts or child categories.**

When attempting to delete a category:
1. Repository checks if category has any child categories
2. If children exist, throws `CategoryHasChildrenException` with count
3. Repository checks if category has any posts (via pivot table)
4. If posts exist, throws `CategoryHasPostsException` with count
5. User must reassign/remove posts and delete children first

This prevents orphaned relationships and enforces data integrity.

```php
// CategoryRepository
public function delete(CategoryInterface $category): void
{
    $childCount = $this->countChildren($category->getId());
    if ($childCount > 0) {
        throw new CategoryHasChildrenException($category, $childCount);
    }

    $postCount = $this->countPosts($category->getId());
    if ($postCount > 0) {
        throw new CategoryHasPostsException($category, $postCount);
    }
    // proceed with deletion
}
```

## CategoryInterface

```php
interface CategoryInterface
{
    public function getId(): ?int;
    public function getName(): string;
    public function getSlug(): string;
    public function getParentId(): ?int;
    public function getParent(): ?CategoryInterface;
    public function getChildren(): array;
    public function getPath(): array; // Returns array of categories from root to this
    public function getCreatedAt(): ?DateTimeImmutable;
    public function getUpdatedAt(): ?DateTimeImmutable;
}
```

## Acceptance Criteria
- All requirements have passing tests
- CategoryInterface defined for extensibility
- Category entity with parent_id self-reference
- CategoryRepositoryInterface and CategoryRepository created
- `isSlugUnique(string $slug, ?int $excludeId): bool` method for slug generation
- Deletion prevented when children or posts exist
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
