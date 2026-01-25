# Task 037: Post Show with Comments Integration

**Status**: pending
**Depends on**: 034, 035, 036
**Retry count**: 0

## Description
Integrate the comment thread and comment form components into the post show view. Creates the complete single post page with full comment functionality.

## Context
- Related files: `packages/blog/resources/views/post/show.latte`
- Patterns to follow: Latte include/embed for components
- Combines post content with interactive comment section

## Requirements (Test Descriptions)
- [ ] `it includes comment thread component after post content`
- [ ] `it includes comment form component`
- [ ] `it passes verified comments to thread component`
- [ ] `it passes post slug to form for action URL`
- [ ] `it displays verification success message when present`
- [ ] `it shows comment count in heading`
- [ ] `it handles reply form display via JavaScript data attribute`
- [ ] `it maintains proper section structure`

## Acceptance Criteria
- All requirements have passing tests
- Full post page renders with comments
- Form submits correctly
- Thread displays properly nested
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
