# Task 008: Post-Category Relationship

**Status**: complete
**Depends on**: 004, 007
**Retry count**: 0

## Description
Create the many-to-many relationship between Posts and Categories via a pivot entity. A post can belong to multiple categories, and a category can contain multiple posts.

## Context
- Related files: `packages/blog/src/Entity/PostCategory.php`, updates to PostRepository
- Patterns to follow: Pivot entity pattern with composite primary key
- Enables category archives showing all posts in a category

## Requirements (Test Descriptions)
- [x] `it creates post category pivot with post_id and category_id`
- [x] `it enforces foreign key to posts table`
- [x] `it enforces foreign key to categories table`
- [x] `it prevents duplicate post category combinations`
- [x] `it attaches category to post`
- [x] `it detaches category from post`
- [x] `it returns all categories for a post`
- [x] `it returns all posts for a category`
- [x] `it syncs categories for a post replacing existing`

## Acceptance Criteria
- All requirements have passing tests
- PostCategoryInterface defined for Preference swapping
- PostCategory pivot entity created
- PostRepository has category relationship methods
- CategoryRepository has post relationship methods
- Code follows Marko standards

## Implementation Notes
Implemented the many-to-many relationship between Posts and Categories:

### Created files:
- `packages/blog/src/Entity/PostCategoryInterface.php` - Interface for Preference swapping
- `packages/blog/src/Entity/PostCategory.php` - Pivot entity with composite primary key

### Modified files:
- `packages/blog/src/Repositories/PostRepository.php` - Added category relationship methods
- `packages/blog/src/Repositories/PostRepositoryInterface.php` - Added interface definitions
- `packages/blog/src/Repositories/CategoryRepository.php` - Added post relationship methods
- `packages/blog/src/Repositories/CategoryRepositoryInterface.php` - Added interface definitions

### Key features:
- PostCategory entity uses #[Table('post_categories')] attribute
- Foreign keys with CASCADE delete to both posts.id and categories.id
- Unique composite index on (post_id, category_id) to prevent duplicates
- attachCategory/detachCategory methods for individual operations
- getCategoriesForPost/getPostsForCategory for querying relationships
- syncCategories for atomic replacement of all category associations

All 2762 tests pass.
