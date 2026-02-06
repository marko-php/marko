# Task 010: marko/admin-auth - AdminAuthMiddleware and RequiresPermission Attribute

**Status**: done
**Depends on**: 007, 009
**Retry count**: 0

## Description
Create `AdminAuthMiddleware` that ensures the user is authenticated via the admin guard and has the required permission. Create `#[RequiresPermission]` attribute that can be placed on controller methods to declare which permission is needed. The middleware reads this attribute and checks the authenticated user's permissions.

## Context
- Related files: `packages/auth/src/Middleware/AuthMiddleware.php`, `packages/routing/src/Middleware/MiddlewareInterface.php`
- AdminAuthMiddleware extends the auth concept but adds permission checking
- It first checks authentication (like AuthMiddleware), then checks permission
- The RequiresPermission attribute stores the permission key string
- Middleware reads the attribute from the matched route's controller method
- If user lacks permission, returns 403 Forbidden (JSON for API, redirect for web)
- Uses wildcard matching from PermissionRegistry (task 007)
- Super admin role bypasses all permission checks

## Requirements (Test Descriptions)
- [x] `it creates RequiresPermission attribute targeting methods with permission key property`
- [x] `it returns 401 when user is not authenticated`
- [x] `it passes through when user is authenticated and no RequiresPermission attribute present`
- [x] `it passes through when user has the required permission`
- [x] `it returns 403 when user lacks the required permission`
- [x] `it passes through for super admin users regardless of permission`
- [x] `it redirects to admin login for unauthenticated web requests`
- [x] `it returns JSON 401 for unauthenticated API requests`
- [x] `it returns JSON 403 for unauthorized API requests`
- [x] `it supports wildcard permission matching via user roles`

## Acceptance Criteria
- All requirements have passing tests
- Middleware follows existing MiddlewareInterface pattern
- Both web (redirect) and API (JSON) response modes work
- Permission checking uses wildcard matching
- Code follows code standards
