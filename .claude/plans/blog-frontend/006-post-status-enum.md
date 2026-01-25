# Task 006: Post Status Enum

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create a PostStatus enum representing the publication state of a post: Draft, Published, or Scheduled. This enum is used by the Post entity to manage content workflow.

## Context
- Related files: `packages/blog/src/Enum/PostStatus.php`
- Patterns to follow: PHP 8.1+ backed enums with string values
- Status flow: Draft → Published, Draft → Scheduled → Published

## Requirements (Test Descriptions)
- [ ] `it has Draft status with value draft`
- [ ] `it has Published status with value published`
- [ ] `it has Scheduled status with value scheduled`
- [ ] `it can be created from string value`
- [ ] `it provides human readable label for each status`
- [ ] `it identifies if status is publicly visible`

## Acceptance Criteria
- All requirements have passing tests
- PostStatus is a backed string enum
- Provides helper methods for status checks
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
