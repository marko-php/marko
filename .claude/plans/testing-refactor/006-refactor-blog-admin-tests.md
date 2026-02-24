# Task 006: Refactor blog admin tests to use marko/testing

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Replace GuardInterface stub, EventDispatcher stubs across 5 admin controller tests, and ConfigRepository stub in blog admin and config tests. Depends on task 001 for FakeGuard.

## Context
- Related files:
  - `packages/blog/tests/Unit/Admin/Api/BlogApiAuthTest.php` — BlogApiStubGuard (lines 97-160, 64 lines)
  - `packages/blog/tests/Unit/Admin/Controllers/CommentAdminControllerTest.php` — EventDispatcher helper (line 629)
  - `packages/blog/tests/Unit/Admin/Controllers/CategoryAdminControllerTest.php` — EventDispatcher helper (line 554)
  - `packages/blog/tests/Unit/Admin/Controllers/AuthorAdminControllerTest.php` — EventDispatcher helper (line 507)
  - `packages/blog/tests/Unit/Admin/Controllers/PostAdminControllerTest.php` — EventDispatcher helper (line 1054)
  - `packages/blog/tests/Unit/Admin/Controllers/TagAdminControllerTest.php` — EventDispatcher helper (line 498)
  - `packages/blog/tests/Config/BlogConfigTest.php` — ConfigRepositoryInterface stub (line 13)

### Replacements
| Old | New |
|---|---|
| `BlogApiStubGuard` (name: 'admin-api') | `new FakeGuard(name: 'admin-api')` |
| `createMockEventDispatcher()` helpers × 5 | `new FakeEventDispatcher()` |
| ConfigRepository anonymous class | `new FakeConfigRepository([...])` |

## Requirements (Test Descriptions)
- [ ] `it uses FakeGuard instead of BlogApiStubGuard in BlogApiAuthTest`
- [ ] `it uses FakeEventDispatcher in all 5 admin controller tests`
- [ ] `it uses FakeConfigRepository in BlogConfigTest`
- [ ] `it preserves all existing test assertions and behaviors`

## Acceptance Criteria
- All existing blog admin tests pass unchanged
- BlogApiStubGuard class removed
- EventDispatcher helper functions removed from 5 admin controller tests
- ConfigRepository stub removed from BlogConfigTest
- Run: `./vendor/bin/pest packages/blog/tests/ --parallel`
