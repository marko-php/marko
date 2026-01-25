# Task 011: Comment Entity

**Status**: pending
**Depends on**: 007, 010
**Retry count**: 0

## Description
Create the Comment entity supporting threaded discussions on posts. Comments have author info (name, email), content, status, and optional parent for nesting. Self-referential parent_id enables comment threading.

## Context
- Related files: `packages/blog/src/Entity/Comment.php`
- Patterns to follow: Self-referential like Category for threading
- Comments belong to a Post and optionally to a parent Comment

## Requirements (Test Descriptions)
- [ ] `it creates comment with post_id author_name author_email and content`
- [ ] `it requires post_id field`
- [ ] `it requires author_name field`
- [ ] `it requires author_email with valid format`
- [ ] `it requires content field with minimum length`
- [ ] `it validates content does not exceed maximum length of 10000 characters`
- [ ] `it has status defaulting to pending`
- [ ] `it has nullable parent_id for threading`
- [ ] `it has verified_at nullable datetime`
- [ ] `it has created_at timestamp`
- [ ] `it returns associated post entity`
- [ ] `it returns parent comment if exists`
- [ ] `it returns child comments`

## Acceptance Criteria
- All requirements have passing tests
- CommentInterface defined for extensibility
- Comment entity with proper database attributes
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
