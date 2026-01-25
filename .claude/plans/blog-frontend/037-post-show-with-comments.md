# Task 037: Post Show with Comments Integration

**Status**: completed
**Depends on**: 034, 035, 036
**Retry count**: 0

## Description
Integrate the comment thread and comment form components into the post show view. Creates the complete single post page with full comment functionality.

## Context
- Related files: `packages/blog/resources/views/post/show.latte`
- Patterns to follow: Latte include/embed for components
- Combines post content with interactive comment section

## Requirements (Test Descriptions)
- [x] `it includes comment thread component after post content`
- [x] `it includes comment form component`
- [x] `it passes verified comments to thread component`
- [x] `it passes post slug to form for action URL`
- [x] `it displays verification success message when present`
- [x] `it shows comment count in heading`
- [x] `it handles reply form display via JavaScript data attribute`
- [x] `it maintains proper section structure`

## Acceptance Criteria
- All requirements have passing tests
- Full post page renders with comments
- Form submits correctly
- Thread displays properly nested
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
