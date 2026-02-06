# Task 024: marko/blog Admin - Blog Admin API Controllers

**Status**: pending
**Depends on**: 018, 019
**Retry count**: 0

## Description
Create JSON API controllers for blog admin operations that work through the admin-api package. These expose the same CRUD operations as the panel controllers but return JSON responses using the ApiResponse helper.

## Context
- Controllers at `packages/blog/src/Admin/Api/`
- API routes under `/admin/api/v1/blog/`
- **PostApiController**: `GET /posts` (list), `POST /posts` (create), `GET /posts/{id}` (show), `PUT /posts/{id}` (update), `DELETE /posts/{id}` (delete), `POST /posts/{id}/publish` (publish)
- **AuthorApiController**: Same CRUD pattern under `/authors`
- **CategoryApiController**: Same CRUD pattern under `/categories`
- **TagApiController**: Same CRUD pattern under `/tags`
- **CommentApiController**: `GET /comments` (list), `GET /comments/{id}` (show), `POST /comments/{id}/verify` (verify), `DELETE /comments/{id}` (delete)
- All use AdminAuthMiddleware with RequiresPermission
- All use ApiResponse helpers for consistent JSON format
- Uses TokenGuard auth (admin-api guard)
- Reuses existing blog repositories for data access

## Requirements (Test Descriptions)
- [ ] `it creates PostApiController with list, show, create, update, delete, publish actions`
- [ ] `it returns paginated JSON list of posts with meta`
- [ ] `it creates post from JSON body and returns 201`
- [ ] `it returns 422 with validation errors for invalid post data`
- [ ] `it creates AuthorApiController with CRUD actions returning JSON`
- [ ] `it creates CategoryApiController with CRUD actions returning JSON`
- [ ] `it creates TagApiController with CRUD actions returning JSON`
- [ ] `it creates CommentApiController with list, show, verify, delete actions`
- [ ] `it requires appropriate blog permissions on each endpoint`
- [ ] `it returns ApiResponse format for all responses`
- [ ] `it returns 401 when bearer token is missing`
- [ ] `it returns 403 when user lacks required permission`

## Acceptance Criteria
- All requirements have passing tests
- API controllers use ApiResponse helpers consistently
- All endpoints properly permission-protected
- JSON response format is consistent across all endpoints
- Reuses blog repositories (no duplicate data access)
- Code follows code standards
