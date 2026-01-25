# Task 020: Comment Lifecycle Events

**Status**: pending
**Depends on**: 011
**Retry count**: 0

## Description
Create events dispatched throughout the comment lifecycle. Events enable observers to react to comment activity (moderation notifications, spam detection, analytics, etc.).

## Context
- Related files: `packages/blog/src/Events/Comment/` directory
- Patterns to follow: Same as post events
- Events dispatched from CommentRepository and verification service

## Requirements (Test Descriptions)
- [ ] `it dispatches CommentCreated event when comment is submitted`
- [ ] `it dispatches CommentVerified event when email is verified`
- [ ] `it dispatches CommentDeleted event when comment is removed`
- [ ] `it includes full comment entity in event data`
- [ ] `it includes associated post in event data`
- [ ] `it includes verification method in CommentVerified event`
- [ ] `it includes timestamp in all events`

## Acceptance Criteria
- All requirements have passing tests
- Event classes created: CommentCreated, CommentVerified, CommentDeleted
- Events contain all useful data for observers
- Repository and service dispatch events appropriately
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
