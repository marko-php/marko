# Task 023: marko/blog Admin - Remaining Admin Latte Templates

**Status**: done
**Depends on**: 022
**Retry count**: 0

## Description
Create the Latte templates for the remaining blog admin views: authors, categories, tags, and comments. These follow the same patterns established in the post templates.

## Context
- **Author templates**: `blog::admin/author/index`, `blog::admin/author/create`, `blog::admin/author/edit`
  - Fields: name, email, bio, slug (auto-generated)
- **Category templates**: `blog::admin/category/index`, `blog::admin/category/create`, `blog::admin/category/edit`
  - Fields: name, slug (auto-generated), parent category dropdown (hierarchical)
  - List shows hierarchy indentation
- **Tag templates**: `blog::admin/tag/index`, `blog::admin/tag/create`, `blog::admin/tag/edit`
  - Fields: name, slug (auto-generated)
- **Comment templates**: `blog::admin/comment/index`, `blog::admin/comment/show`
  - List shows: commenter name, email, post title, status, date
  - Show view: full comment content with verify/delete actions

## Requirements (Test Descriptions)
- [x] `it creates author index, create, and edit templates`
- [x] `it creates category index, create, and edit templates with parent dropdown`
- [x] `it creates tag index, create, and edit templates`
- [x] `it creates comment index and show templates`
- [x] `it shows category hierarchy with indentation in category list`
- [x] `it includes verify and delete actions on comment show template`
- [x] `it shows comment status badge on comment list`
- [x] `it extends admin-panel base layout for all templates`
- [x] `it includes pagination on all list templates`
- [x] `it includes flash message display on all templates`

## Acceptance Criteria
- All templates are valid Latte syntax
- Templates follow patterns from post templates (task 022)
- All extend admin-panel base layout
- Forms have proper method and action attributes
- Code follows code standards
