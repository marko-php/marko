# Task 035: Comment Thread Component

**Status**: pending
**Depends on**: 013
**Retry count**: 0

## Description
Create a Latte component for displaying threaded comments. Recursively renders comment trees with proper indentation and reply links.

## Context
- Related files: `packages/blog/resources/views/components/comment-thread.latte`
- Patterns to follow: Recursive Latte component
- Must handle nested comments up to configured max depth

## Requirements (Test Descriptions)
- [ ] `it renders single comment with author name and content`
- [ ] `it displays comment created date`
- [ ] `it renders nested replies with indentation`
- [ ] `it respects max depth configuration`
- [ ] `it shows reply link for comments under max depth`
- [ ] `it hides reply link at max depth`
- [ ] `it shows message when no comments`
- [ ] `it has semantic HTML structure`
- [ ] `it includes proper ARIA labels for accessibility`
- [ ] `it displays comment count`

## Acceptance Criteria
- All requirements have passing tests
- Recursive rendering works correctly
- Respects max depth from BlogConfig
- Styling-agnostic with CSS classes
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
