# Task 008: Post-Category Relationship

**Status**: pending
**Depends on**: 004, 007
**Retry count**: 0

## Description
Create the many-to-many relationship between Posts and Categories via a pivot entity. A post can belong to multiple categories, and a category can contain multiple posts.

## Context
- Related files: `packages/blog/src/Entity/PostCategory.php`, updates to PostRepository
- Patterns to follow: Pivot entity pattern with composite primary key
- Enables category archives showing all posts in a category

## Requirements (Test Descriptions)
- [ ] `it creates post category pivot with post_id and category_id`
- [ ] `it enforces foreign key to posts table`
- [ ] `it enforces foreign key to categories table`
- [ ] `it prevents duplicate post category combinations`
- [ ] `it attaches category to post`
- [ ] `it detaches category from post`
- [ ] `it returns all categories for a post`
- [ ] `it returns all posts for a category`
- [ ] `it syncs categories for a post replacing existing`

## Acceptance Criteria
- All requirements have passing tests
- PostCategoryInterface defined for Preference swapping
- PostCategory pivot entity created
- PostRepository has category relationship methods
- CategoryRepository has post relationship methods
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
