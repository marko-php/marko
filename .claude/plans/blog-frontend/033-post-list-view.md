# Task 033: Post List View

**Status**: pending
**Depends on**: 024, 032
**Retry count**: 0

## Description
Create the Latte view template for the blog post list page. Displays posts with title, summary, author, date, and includes pagination component.

## Context
- Related files: `packages/blog/resources/views/post/index.latte` (exists, update)
- Patterns to follow: Existing Latte templates in blog package
- Styling-agnostic with semantic HTML and CSS classes

## Requirements (Test Descriptions)
- [ ] `it renders list of posts`
- [ ] `it displays post title as link to full post`
- [ ] `it displays post summary`
- [ ] `it displays author name as link to author archive`
- [ ] `it displays published date`
- [ ] `it displays post categories as links`
- [ ] `it displays post tags as links`
- [ ] `it includes pagination component`
- [ ] `it shows message when no posts found`
- [ ] `it has semantic HTML structure`

## Acceptance Criteria
- All requirements have passing tests
- Template renders correctly with test data
- All links use correct URL structure
- Styling-agnostic (classes only, no inline styles)
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
