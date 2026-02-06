# Task 021: marko/blog Admin - Author/Category/Tag/Comment Admin Controllers

**Status**: pending
**Depends on**: 020
**Retry count**: 0

## Description
Create the remaining admin controllers for the blog: AuthorAdminController, CategoryAdminController, TagAdminController, and CommentAdminController. These follow the same patterns established in PostAdminController.

## Context
- Controllers at `packages/blog/src/Admin/Controllers/`
- **AuthorAdminController**: CRUD for authors. Routes under `/admin/blog/authors`. Permissions: `blog.authors.*`
- **CategoryAdminController**: CRUD for categories with parent selection (hierarchical). Routes under `/admin/blog/categories`. Permissions: `blog.categories.*`
- **TagAdminController**: CRUD for tags. Routes under `/admin/blog/tags`. Permissions: `blog.tags.*`
- **CommentAdminController**: List, view, verify, delete for comments (no create - comments come from frontend). Routes under `/admin/blog/comments`. Permissions: `blog.comments.*`
- All use AdminAuthMiddleware and RequiresPermission
- All use existing repositories from the blog package

## Requirements (Test Descriptions)
- [ ] `it creates AuthorAdminController with list, create, store, edit, update, delete actions`
- [ ] `it requires blog.authors.view permission for author list`
- [ ] `it creates CategoryAdminController with list, create, store, edit, update, delete actions`
- [ ] `it supports parent category selection in category create/edit`
- [ ] `it requires blog.categories.view permission for category list`
- [ ] `it creates TagAdminController with list, create, store, edit, update, delete actions`
- [ ] `it requires blog.tags.view permission for tag list`
- [ ] `it creates CommentAdminController with list, view, verify, delete actions`
- [ ] `it requires blog.comments.view permission for comment list`
- [ ] `it verifies pending comment via CommentAdminController verify action`
- [ ] `it applies AdminAuthMiddleware to all admin controller routes`
- [ ] `it dispatches appropriate events on create, update, delete operations`

## Acceptance Criteria
- All requirements have passing tests
- All four controllers follow PostAdminController patterns
- Proper permissions on every route
- All controllers use existing blog repositories
- Code follows code standards
