# Task 013: Comment Repository

**Status**: pending
**Depends on**: 011
**Retry count**: 0

## Description
Create the CommentRepository with methods for retrieving comments with proper threading support. Must efficiently load comment trees for posts while respecting the configured max depth.

## Context
- Related files: `packages/blog/src/Repositories/CommentRepository.php`
- Patterns to follow: Existing repository patterns, uses BlogConfig for max_depth
- Threading requires building a tree structure from flat results

## Requirements (Test Descriptions)
- [ ] `it finds comment by id`
- [ ] `it finds all verified comments for a post`
- [ ] `it finds pending comments for a post`
- [ ] `it returns comments as threaded tree structure`
- [ ] `it respects max depth configuration when building tree`
- [ ] `it orders comments by created_at ascending`
- [ ] `it counts total comments for a post`
- [ ] `it counts verified comments for a post`
- [ ] `it finds comments by author email`
- [ ] `it calculates depth of a comment in thread`

## Acceptance Criteria
- All requirements have passing tests
- CommentRepositoryInterface defined
- CommentRepository implements tree building logic
- Uses BlogConfig for max_depth setting
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
