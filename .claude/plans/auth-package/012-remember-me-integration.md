# Task 012: Session Guard "Remember Me" Integration

**Status**: completed
**Depends on**: 008, 011
**Retry count**: 0

## Description
Integrate remember me functionality into SessionGuard using RememberTokenManager.

## Context
- When remember=true on login, create and store remember token
- Check remember token cookie on subsequent requests
- Clear remember token on logout

## Requirements (Test Descriptions)
- [x] `it creates remember token on login with remember flag`
- [x] `it stores remember token in user provider`
- [x] `it authenticates via remember token cookie`
- [x] `it clears remember token on logout`
- [x] `it regenerates remember token on each use`
- [x] `it does not create token when remember is false`
- [x] `it handles missing remember token gracefully`

## Acceptance Criteria
- All requirements have passing tests
- Remember tokens are rotated on each use (security)
- Logout clears both session and remember token

## Implementation Notes
- Created CookieJarInterface at packages/auth/src/Contracts/CookieJarInterface.php for cookie abstraction
- Updated SessionGuard constructor to accept optional CookieJarInterface and RememberTokenManager
- Added `remember` parameter to login() method (defaults to false)
- On login with remember=true, generates token, hashes it, stores hash in provider, and sets cookie with "id|token" format
- On user() call when no session user exists, checks remember cookie and validates token against stored hash
- Regenerates token on each successful remember-cookie authentication (prevents replay attacks)
- On logout, clears both the remember cookie and the stored token in provider
- All edge cases handled gracefully (missing cookie, invalid format, token mismatch, user not found)
