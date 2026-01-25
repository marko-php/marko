# Task 034: Post Show View

**Status**: pending
**Depends on**: 025
**Retry count**: 0

## Description
Create the Latte view template for single post display. Shows full post content, author info, categories, tags, and prepared for comment section integration.

## Context
- Related files: `packages/blog/resources/views/post/show.latte` (exists, update)
- Patterns to follow: Existing Latte templates
- Comment section added in later task

## Requirements (Test Descriptions)
- [ ] `it renders post title`
- [ ] `it renders full post content`
- [ ] `it displays author name with link to archive`
- [ ] `it displays author bio`
- [ ] `it displays published date`
- [ ] `it displays last updated date when updated_at is after published_at`
- [ ] `it hides last updated date when post has not been modified`
- [ ] `it displays categories as links`
- [ ] `it displays tags as links`
- [ ] `it has placeholder for comment section`
- [ ] `it has semantic HTML with article element`
- [ ] `it includes proper heading hierarchy`

## Acceptance Criteria
- All requirements have passing tests
- Template renders correctly with test data
- Semantic HTML structure
- Styling-agnostic
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
