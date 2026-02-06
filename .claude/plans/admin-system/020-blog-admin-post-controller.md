# Task 020: marko/blog Admin - PostAdminController (List, Create, Edit, Delete)

**Status**: complete
**Depends on**: 010, 019
**Retry count**: 0

## Description
Create the `PostAdminController` in the blog package that provides CRUD operations for posts through the admin panel. Routes are under the admin prefix, protected by AdminAuthMiddleware with appropriate permissions.

## Context
- Controller at `packages/blog/src/Admin/Controllers/PostAdminController.php`
- Routes: `GET /admin/blog/posts` (list), `GET /admin/blog/posts/create` (create form), `POST /admin/blog/posts` (store), `GET /admin/blog/posts/{id}/edit` (edit form), `PUT /admin/blog/posts/{id}` (update), `DELETE /admin/blog/posts/{id}` (delete), `POST /admin/blog/posts/{id}/publish` (publish)
- Each route has `#[RequiresPermission]` with appropriate blog permission
- All routes use AdminAuthMiddleware
- Uses existing PostRepository, AuthorRepository, CategoryRepository, TagRepository
- Uses ViewInterface for rendering (templates in blog package: `blog::admin/post/index`, etc.)
- List endpoint supports pagination
- Create/edit forms include: title, content, summary, author selection, category checkboxes, tag selection, status

## Requirements (Test Descriptions)
- [x] `it lists paginated posts on GET /admin/blog/posts with blog.posts.view permission`
- [x] `it renders create form on GET /admin/blog/posts/create with blog.posts.create permission`
- [x] `it creates new post on POST /admin/blog/posts with valid data`
- [x] `it returns validation errors on POST /admin/blog/posts with invalid data`
- [x] `it renders edit form on GET /admin/blog/posts/{id}/edit with blog.posts.edit permission`
- [x] `it returns 404 when editing non-existent post`
- [x] `it updates post on PUT /admin/blog/posts/{id} with valid data`
- [x] `it deletes post on DELETE /admin/blog/posts/{id} with blog.posts.delete permission`
- [x] `it publishes post on POST /admin/blog/posts/{id}/publish with blog.posts.publish permission`
- [x] `it requires AdminAuthMiddleware on all routes`
- [x] `it syncs categories and tags on create and update`
- [x] `it dispatches PostCreated and PostUpdated events`

## Acceptance Criteria
- All requirements have passing tests
- Controller follows existing controller patterns
- All routes properly permission-protected
- CRUD operations use existing repositories and dispatch events
- Code follows code standards
