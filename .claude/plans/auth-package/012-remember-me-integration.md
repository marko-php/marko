# Task 012: Session Guard "Remember Me" Integration

**Status**: pending
**Depends on**: 008, 011
**Retry count**: 0

## Description
Integrate remember me functionality into SessionGuard using RememberTokenManager.

## Context
- When remember=true on login, create and store remember token
- Check remember token cookie on subsequent requests
- Clear remember token on logout

## Requirements (Test Descriptions)
- [ ] `it creates remember token on login with remember flag`
- [ ] `it stores remember token in user provider`
- [ ] `it authenticates via remember token cookie`
- [ ] `it clears remember token on logout`
- [ ] `it regenerates remember token on each use`
- [ ] `it does not create token when remember is false`
- [ ] `it handles missing remember token gracefully`

## Acceptance Criteria
- All requirements have passing tests
- Remember tokens are rotated on each use (security)
- Logout clears both session and remember token

## Implementation Notes
(Left blank - filled in by programmer during implementation)
