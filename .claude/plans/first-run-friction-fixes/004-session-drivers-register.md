# Task 004: Session drivers register the binding, middleware, and ordering

**Status**: complete
**Depends on**: 003
**Retry count**: 0

## Description
Now that `marko/session` is passive (Task 003), the session runtime must light up only when a driver is installed. Move the relocated registration into each session driver's `module.php`: bind `SessionInterface => Session` as a singleton, register `SessionMiddleware` as global middleware, and carry the page-cache `sequence.after: [marko/page-cache]` ordering — alongside the `SessionHandlerInterface` binding each driver already declares.

## Context
- Related files:
  - `packages/session-file/module.php` (currently binds only `SessionHandlerInterface => FileSessionHandler`)
  - `packages/session-database/module.php` (currently binds only `SessionHandlerInterface => DatabaseSessionHandler`)
  - `packages/session-file/tests/`, `packages/session-database/tests/`
  - The relocated block:
    ```php
    'sequence'   => ['after' => ['marko/page-cache']],
    'singletons' => [SessionInterface::class => Session::class],
    'globalMiddleware' => [SessionMiddleware::class],
    ```
- This duplication across the two drivers is intentional and explicit — a driver is what makes sessions work, including wiring the request lifecycle.
- **Mutually-exclusive drivers (no new handling needed):** `session-file` and `session-database` already both bind `SessionHandlerInterface` at the same `vendor` source priority, so installing BOTH already throws `BindingConflictException` today (they are mutually exclusive by design). Adding `SessionInterface => Session` to both therefore introduces NO new conflict path — do not add dedup/guard logic for the two-drivers-installed case; it remains a loud, pre-existing conflict. `GlobalMiddlewareResolver` already de-dupes the same middleware class by design, so the duplicated `globalMiddleware` entry is harmless.
- Pattern to follow: `marko/cache-file` / other driver `module.php` files.

## Requirements (Test Descriptions)
- [x] `it binds SessionInterface to Session when the file session driver is installed`
- [x] `it registers the session global middleware when the file session driver is installed`
- [x] `it binds SessionInterface to Session when the database session driver is installed`
- [x] `it registers the session global middleware when the database session driver is installed`
- [x] `it starts a session and sets a session cookie end-to-end with the file driver installed`
- [x] `it orders the session middleware after page-cache when the file driver and page-cache are both installed`

## Acceptance Criteria
- Installing a session driver fully restores prior session behavior (start/save, `Set-Cookie`).
- With a driver installed, a session-using route works end-to-end.
- All requirements have passing tests; lint clean; no coverage decrease.

## Implementation Notes
- Added `sequence`, `singletons`, and `globalMiddleware` to both driver module.php files alongside the existing `bindings` array.
- New tests in `session-file/tests/ModuleTest.php` (4 tests): binding, middleware registration, end-to-end lifecycle, and sequence ordering.
- New tests appended to `session-database/tests/ModuleTest.php` (2 tests): binding and middleware registration.
- End-to-end test verifies `FileSessionHandler` + `Session` + `SessionMiddleware` complete the full start/write/save cycle with a real temp directory.
- All 6 requirements have passing tests; lint clean (php-cs-fixer: 0 files changed); full suite: 6837 passed.
