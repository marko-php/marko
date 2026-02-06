# Task 022: marko/blog Admin - Post Admin Latte Templates

**Status**: completed
**Depends on**: 013, 020
**Retry count**: 0

## Description
Create the Latte templates for post admin views: list (index), create form, edit form, and delete confirmation. These extend the admin-panel base layout and provide the UI for post management.

## Context
- Templates at `packages/blog/resources/views/admin/post/`
- Template names: `blog::admin/post/index`, `blog::admin/post/create`, `blog::admin/post/edit`
- All extend `admin-panel::layout/base` layout
- Post list: table with columns for title, author, status, published date, actions (edit, delete)
- Create/edit forms: title input, content textarea, summary textarea, author dropdown, category checkboxes, tag multi-select, status dropdown, scheduled date (if scheduled)
- Pagination for list view (use existing PaginatedResult structure)
- Flash messages for success/error after operations
- Forms use POST method with appropriate action URLs

## Requirements (Test Descriptions)
- [x] `it creates index template with posts table and pagination`
- [x] `it creates create template with form fields for all post properties`
- [x] `it creates edit template pre-populated with existing post data`
- [x] `it includes author dropdown populated from passed authors array`
- [x] `it includes category checkboxes populated from passed categories array`
- [x] `it includes tag selection populated from passed tags array`
- [x] `it includes status dropdown with Draft, Published, Scheduled options`
- [x] `it shows action buttons for edit and delete on each row`
- [x] `it extends admin-panel base layout`
- [x] `it includes flash message display`

## Acceptance Criteria
- All templates are valid Latte syntax
- Templates extend admin-panel base layout correctly
- Forms have proper method and action attributes
- Templates are semantic HTML
- Code follows code standards
