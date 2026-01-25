# Task 009: Post-Tag Relationship

**Status**: pending
**Depends on**: 005, 007
**Retry count**: 0

## Description
Create the many-to-many relationship between Posts and Tags via a pivot entity. Similar to categories but for the flat tag taxonomy.

## Context
- Related files: `packages/blog/src/Entity/PostTag.php`, updates to PostRepository
- Patterns to follow: Same pivot pattern as PostCategory
- Enables tag archives showing all posts with a tag

## Requirements (Test Descriptions)
- [ ] `it creates post tag pivot with post_id and tag_id`
- [ ] `it enforces foreign key to posts table`
- [ ] `it enforces foreign key to tags table`
- [ ] `it prevents duplicate post tag combinations`
- [ ] `it attaches tag to post`
- [ ] `it detaches tag from post`
- [ ] `it returns all tags for a post`
- [ ] `it returns all posts for a tag`
- [ ] `it syncs tags for a post replacing existing`

## Acceptance Criteria
- All requirements have passing tests
- PostTagInterface defined for Preference swapping
- PostTag pivot entity created
- PostRepository has tag relationship methods
- TagRepository has post relationship methods
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
