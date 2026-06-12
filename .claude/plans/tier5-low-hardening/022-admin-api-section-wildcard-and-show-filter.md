# Task 022: admin-api section visibility ignores wildcards and show() skips filter

**Status**: complete
**Depends on**: [none]
**Retry count**: 0

## Description
`SectionController` (in `marko/admin-api`) decides section/menu visibility with `userCanAccessSection()`, which checks `$user->hasPermission($permission)` directly. `AdminUser::hasPermission()` only does a super-admin bypass plus an exact permission-key check — it does NOT honor wildcard grants. But the `AdminAuthMiddleware` (in `marko/admin-auth`) that gates the actual routes DOES honor wildcards: after the exact `hasPermission()` check it iterates the user's permission keys and calls `PermissionRegistry::matches($permissionKey, $requiredPermission)`. So a user granted `catalog.*` can reach a catalog route (middleware lets them through) but is HIDDEN from the section menu (the controller's exact-match filter rejects them). Two fixes:
1. Use wildcard-aware permission matching for section visibility (consult the same `PermissionRegistry::matches()` the middleware uses), so a `catalog.*` user sees catalog sections.
2. `show($id)` applies NO permission filter at all — it returns any section by id regardless of the user's permissions. Apply the same visibility check `index()` uses to `show()` (return not-found / forbidden when the user can't access the section).

## Description-note
The menu and the route gate must agree on who can see what; today the menu is stricter than the gate, which is both a correctness bug (wildcard-granted users get an empty/short menu) and an inconsistency between `index()` (filtered) and `show()` (unfiltered). The fix routes both controller methods through the SAME wildcard-aware matching the middleware already implements.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/admin-api/src/Controller/SectionController.php` (`index()` ~31-57 — filters via `userCanAccessSection()`; `show()` ~62-90 — NO permission filter, returns the section unconditionally; `userCanAccessSection()` ~91-110 — `foreach` menu items, `$permission === '' || $user->hasPermission($permission)`; constructor injects only `AdminSectionRegistryInterface` + `GuardInterface`)
  - REFERENCE (the wildcard pattern to reuse): `/Users/markshust/Sites/marko/packages/admin-auth/src/Middleware/AdminAuthMiddleware.php` (~57-66 — `if ($user->hasPermission($requiredPermission)) {...}` then iterates the user's permission keys and `$this->permissionRegistry->matches($permissionKey, $requiredPermission)`; injects `PermissionRegistryInterface` ~24)
  - `/Users/markshust/Sites/marko/packages/admin-auth/src/Contracts/PermissionRegistryInterface.php` (the `matches(string $pattern, string $key): bool` contract) and `/Users/markshust/Sites/marko/packages/admin-auth/src/PermissionRegistry.php` (the wildcard implementation)
  - `/Users/markshust/Sites/marko/packages/admin-auth/src/Entity/AdminUser.php` (`hasPermission()` ~115-121 — super-admin bypass via `array_any($roles, isSuperAdmin)` then exact check; confirm the exact-match path and how the user's permission keys are exposed for wildcard iteration)
  - Tests: `/Users/markshust/Sites/marko/packages/admin-api/tests/` (locate the existing `SectionControllerTest.php`)
- Verified findings (source-confirmed — note PACKAGE drift):
  - `AdminAuthMiddleware` and `PermissionRegistry`/`PermissionRegistryInterface` live in `packages/admin-auth/`, NOT `packages/admin-api/`. The original finding's path (`packages/admin-api/src/.../AdminAuthMiddleware`) is wrong; the cross-package reference above is correct.
  - `SectionController::index()` filters sections with `userCanAccessSection()`; `show()` does not filter at all. `userCanAccessSection()` uses bare `$user->hasPermission(...)`.
  - `SectionController` currently injects `AdminSectionRegistryInterface` + `GuardInterface` only — it does NOT have access to `PermissionRegistryInterface` yet. Adding wildcard matching requires injecting `PermissionRegistryInterface` into the controller.
  - `AdminAuthMiddleware` proves the canonical "exact OR wildcard" matching shape to mirror.
- Patterns to follow:
  - Inject `PermissionRegistryInterface $permissionRegistry` into `SectionController` (constructor promotion; it is `readonly class` — keep it readonly). Update the existing controller-construction test helpers/bindings accordingly.
  - In `userCanAccessSection()`, a menu item is visible when: its permission is `''`, OR `$user->hasPermission($permission)` (exact/super-admin), OR the user has a permission key that wildcard-`matches()` the required permission — mirroring `AdminAuthMiddleware`'s order. Read how the middleware enumerates the user's permission keys and reuse the same source.
  - Apply the same per-section visibility check at the top of `show($id)`: after resolving the section, if the user is an `AdminUserInterface` and `!userCanAccessSection($user, $section)`, return the not-found response (`ApiResponse::notFound(...)`, matching the existing not-found shape) so an inaccessible section is not disclosed. Preserve the existing `AdminException`→not-found behavior for unknown ids.
  - Do not change the route attributes or `ApiResponse` contract.

## Requirements (Test Descriptions)
- [x] `it shows catalog sections to a user granted the catalog wildcard permission`
- [x] `it hides sections the user has no matching permission for`
- [x] `it shows a section to a user with the exact permission`
- [x] `it enforces the permission filter in show for an inaccessible section`
- [x] `it returns the section from show for an accessible section`

## Acceptance Criteria
- A user with `catalog.*` sees catalog sections in both `index()` and `show()`.
- `show($id)` returns not-found for a section the user cannot access (parity with `index()`'s filter).
- Exact-permission and super-admin users are unaffected.
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- Injected `PermissionRegistryInterface $permissionRegistry` into `SectionController` (readonly class, constructor promotion).
- Updated `userCanAccessSection()` to mirror `AdminAuthMiddleware::userHasPermission()`: after the exact `$user->hasPermission()` check, iterates the user's permission keys with `$permissionRegistry->matches($permissionKey, $permission)` via `array_any()`.
- Added permission visibility check at the top of `show()`: after resolving the section, if the user is an `AdminUserInterface` and `!userCanAccessSection($user, $section)`, returns `ApiResponse::notFound(...)` - same shape as the unknown-id not-found response.
- All existing tests updated to pass `permissionRegistry: new PermissionRegistry()` in controller construction.
- Requirements 2, 3, and 5 passed immediately after Requirement 1's implementation (the code covered those cases already); only Requirement 4 required additional code (the `show()` visibility filter).
