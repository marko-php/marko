# Task 020: Comment Lifecycle Events

**Status**: completed
**Depends on**: 011
**Retry count**: 0

## Description
Create events dispatched throughout the comment lifecycle. Events enable observers to react to comment activity (moderation notifications, spam detection, analytics, etc.).

## Context
- Related files: `packages/blog/src/Events/Comment/` directory
- Patterns to follow: Same as post events
- Events dispatched from CommentRepository and verification service

## Requirements (Test Descriptions)
- [x] `it dispatches CommentCreated event when comment is submitted`
- [x] `it dispatches CommentVerified event when email is verified`
- [x] `it dispatches CommentDeleted event when comment is removed`
- [x] `it includes full comment entity in event data`
- [x] `it includes associated post in event data`
- [x] `it includes verification method in CommentVerified event`
- [x] `it includes timestamp in all events`

## Acceptance Criteria
- All requirements have passing tests
- Event classes created: CommentCreated, CommentVerified, CommentDeleted
- Events contain all useful data for observers
- Repository and service dispatch events appropriately
- Code follows Marko standards

## Implementation Notes
Created three event classes following the same pattern as post events:

1. **CommentCreated** - Dispatched from CommentRepository::save() when a new comment is created
2. **CommentVerified** - Dispatched from CommentVerificationService::markAsVerified() with verification method
3. **CommentDeleted** - Dispatched from CommentRepository::delete() when a comment is removed

All events include:
- Full comment entity (CommentInterface)
- Associated post entity (PostInterface)
- Timestamp (DateTimeImmutable, defaults to current time)
- CommentVerified also includes verificationMethod (string)

New files created:
- packages/blog/src/Events/Comment/CommentCreated.php
- packages/blog/src/Events/Comment/CommentVerified.php
- packages/blog/src/Events/Comment/CommentDeleted.php
- packages/blog/src/Services/CommentVerificationService.php
- packages/blog/src/Repositories/CommentRepository.php
- packages/blog/tests/Events/Comment/CommentLifecycleEventsTest.php

Note: PreferenceOverrideTest.php has a pre-existing failure unrelated to this task (PostController constructor signature mismatch).
