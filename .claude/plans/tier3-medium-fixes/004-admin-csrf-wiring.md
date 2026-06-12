# Task 004: Wire security CSRF middleware onto admin state-changing routes

**Status**: complete
**Depends on**: [none]
**Retry count**: 0

## Description
The admin panel exposes state-changing POST routes (`/admin/login`, `/admin/logout`) with no CSRF protection. The `security` package already ships a working `CsrfMiddleware` (validates non-safe methods via `_token`/`X-CSRF-TOKEN`) and `CsrfTokenManager`, bound in `security/module.php`. Apply the existing `CsrfMiddleware` to the framework's own state-changing admin routes via the `#[Middleware]` attribute and expose a CSRF token value to the login view so the form can submit it. Do NOT build new CSRF machinery.

### Scope correction (VERIFIED at review time)
`admin-api`'s `MeController` and `SectionController` define **only `#[Get]` routes** (`/admin/api/v1/me`, `/admin/api/v1/sections`, `/admin/api/v1/sections/{id}`). `CsrfMiddleware` is a no-op on GET/HEAD/OPTIONS, so wiring it onto admin-api would protect nothing and would violate the "no pseudo-functionality" principle. **This task is scoped to `admin-panel` only.** Do NOT add `marko/security` to `admin-api/composer.json` and do NOT add a CSRF test for admin-api — there is no state-changing admin-api route to protect. If/when admin-api gains a non-safe route, CSRF wiring is added then.

## Context
- Related files: `packages/admin-panel/src/Controller/LoginController.php` (authenticate `#[Post('/admin/login')]` ~36-53, logout `#[Post('/admin/logout')]` ~55-62, view render passes `loginUrl`), `packages/security/src/Middleware/CsrfMiddleware.php` (SAFE_METHODS = GET/HEAD/OPTIONS; validate), `packages/security/src/CsrfTokenManager.php` (`get()`/`validate()` — token stored in session under `_csrf_token`), `packages/security/src/Exceptions/CsrfTokenMismatchException.php`, `packages/routing/src/Attributes/Middleware.php` (TARGET_CLASS|TARGET_METHOD, single or array), `packages/admin-panel/composer.json`
- Patterns to follow: apply `#[Middleware(CsrfMiddleware::class)]` at the **method level** on `authenticate()` and `logout()` (NOT class level — `showLoginForm()` is GET and must stay reachable without a token); add `"marko/security": "self.version"` to the **admin-panel** composer require only (no hardcoded version); inject `CsrfTokenManagerInterface` into `LoginController` and pass `csrfToken` (from `->get()`) to the login view; the GET login form must call `->get()` so the same session-stored token validates on the subsequent POST.

## Requirements (Test Descriptions)
- [x] `it rejects a POST to the admin login route when no CSRF token is supplied`
- [x] `it rejects a POST to the admin login route when the CSRF token is invalid`
- [x] `it allows a POST to the admin login route when a valid CSRF token is supplied`
- [x] `it rejects a POST to the admin logout route when no valid CSRF token is supplied`
- [x] `it exposes a CSRF token value to the login view so the form can submit it`
- [x] `it does not require a CSRF token for the GET login form route`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- Added `#[Middleware(CsrfMiddleware::class)]` at method level on `authenticate()` and `logout()` in `LoginController`
- `showLoginForm()` (GET) intentionally has no middleware attribute
- Injected `CsrfTokenManagerInterface` as 4th constructor parameter in `LoginController`
- `csrfToken` exposed to view via `$this->csrfTokenManager->get()` in `showLoginForm()`
- Added `"marko/security": "self.version"` to `packages/admin-panel/composer.json` require
- All 6 tests in `LoginControllerTest.php` verify attribute presence via reflection + middleware behavior
