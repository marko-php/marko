# Task 009: Post-Tag Relationship

**Status**: complete
**Depends on**: 005, 007
**Retry count**: 0

## Description
Create the many-to-many relationship between Posts and Tags via a pivot entity. Similar to categories but for the flat tag taxonomy.

## Context
- Related files: `packages/blog/src/Entity/PostTag.php`, updates to PostRepository
- Patterns to follow: Same pivot pattern as PostCategory
- Enables tag archives showing all posts with a tag

## Requirements (Test Descriptions)
- [x] `it creates post tag pivot with post_id and tag_id`
- [x] `it enforces foreign key to posts table`
- [x] `it enforces foreign key to tags table`
- [x] `it prevents duplicate post tag combinations`
- [x] `it attaches tag to post`
- [x] `it detaches tag from post`
- [x] `it returns all tags for a post`
- [x] `it returns all posts for a tag`
- [x] `it syncs tags for a post replacing existing`

## Acceptance Criteria
- All requirements have passing tests
- PostTagInterface defined for Preference swapping
- PostTag pivot entity created
- PostRepository has tag relationship methods
- TagRepository has post relationship methods
- Code follows Marko standards

## Implementation Notes
Implementation completed with the following files:

**Entity:**
- `packages/blog/src/Entity/PostTag.php` - Pivot entity with foreign keys to posts and tags
- `packages/blog/src/Entity/PostTagInterface.php` - Interface for Preference swapping

**Repository Updates:**
- `packages/blog/src/Repositories/PostRepository.php` - Added attachTag, detachTag, getTagsForPost, syncTags methods
- `packages/blog/src/Repositories/PostRepositoryInterface.php` - Added corresponding interface methods
- `packages/blog/src/Repositories/TagRepository.php` - Added getPostsForTag method
- `packages/blog/src/Repositories/TagRepositoryInterface.php` - Added corresponding interface method

**Tests:**
- `packages/blog/tests/Entity/PostTagTest.php` - 4 tests for entity structure
- `packages/blog/tests/Repositories/PostTagRelationshipTest.php` - 5 tests for relationship methods

All 9 tests pass. Full test suite (2762 tests) passes.
