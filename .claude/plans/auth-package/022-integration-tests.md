# Task 022: Integration Tests

**Status**: completed
**Depends on**: 016, 017
**Retry count**: 0

## Description
Create integration tests verifying the complete auth flow works together.

## Context
- Test full login/logout flow
- Test remember me flow
- Test guard switching
- Verify module bindings work

## Requirements (Test Descriptions)
- [x] `complete login flow works`
- [x] `complete logout flow works`
- [x] `remember me creates and uses token`
- [x] `guard switching works correctly`
- [x] `module bindings resolve correctly`
- [x] `config loading works`
- [x] `events dispatched during auth flow`

## Acceptance Criteria
- All requirements have passing tests
- End-to-end flows verified
- All components integrate properly

## Implementation Notes
Created comprehensive integration tests in `packages/auth/tests/Integration/AuthFlowIntegrationTest.php`:

1. **complete login flow works** - Tests the full authentication cycle through AuthManager: initial unauthenticated state, attempt() with valid credentials, and verification of authenticated state (check(), user(), id()).

2. **complete logout flow works** - Tests login followed by logout, verifying all authentication state is properly cleared.

3. **remember me creates and uses token** - Tests that login with remember=true creates a remember token via RememberTokenManager, stores it in cookies, and that the token validates correctly against the stored hash.

4. **guard switching works correctly** - Tests AuthManager with multiple guards (web/session, api/token, admin/session), verifying correct guard types, names, instance isolation, and guard-scoped authentication.

5. **module bindings resolve correctly** - Tests the module.php file exports proper bindings for PasswordHasherInterface and AuthManager as closures.

6. **config loading works** - Tests AuthConfig correctly reads all configuration values: default guard/provider, guards array, providers array, bcrypt cost, and remember config.

7. **events dispatched during auth flow** - Tests LoginEvent, LogoutEvent, and FailedLoginEvent are dispatched at the correct points in the auth flow with correct data.
