# Task 001: Fix SessionGuard Guard-Scoped Session Keys

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
The current `SessionGuard` uses a hardcoded `auth_user_id` session key. When multiple session guards exist (e.g., `web` and `admin`), they share the same session key, causing auth state collisions. Change the session key to be guard-name-scoped: `auth_{guardName}_user_id`.

## Context
- Related files: `packages/auth/src/Guard/SessionGuard.php`, `packages/auth/tests/Unit/Guard/SessionGuardTest.php`, `packages/auth/tests/Unit/Guard/SessionGuardEventDispatchingTest.php`, `packages/auth/tests/Integration/AuthFlowIntegrationTest.php`
- The `SessionGuard` constructor already accepts a `$name` parameter (default: `'session'`)
- The session key is currently `private const string SESSION_KEY = 'auth_user_id'`
- Change to a dynamic key: `'auth_' . $this->name . '_user_id'`
- Update all tests that reference `auth_user_id` to use the guard-name-scoped key
- This is safe because there are no production deployments

## Requirements (Test Descriptions)
- [ ] `it uses guard-name-scoped session key format auth_{name}_user_id`
- [ ] `it stores user id under scoped session key on login`
- [ ] `it retrieves user id from scoped session key on check`
- [ ] `it removes scoped session key on logout`
- [ ] `it isolates session state between two guards with different names`
- [ ] `it defaults to auth_session_user_id when guard name is session`

## Acceptance Criteria
- All requirements have passing tests
- All existing auth tests updated and passing
- No hardcoded `auth_user_id` references remain in source code
- Code follows code standards
