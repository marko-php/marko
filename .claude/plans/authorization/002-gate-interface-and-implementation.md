# Task 002: GateInterface and Gate Implementation

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the Gate — the central authorization service. `GateInterface` defines the contract for registering abilities (closures) and checking them. The `Gate` implementation resolves the current user from the auth guard and passes it to ability closures.

## Context
- Related files: `packages/auth/src/AuthManager.php`, `packages/auth/src/Contracts/GuardInterface.php`
- Gate closures receive `(?AuthorizableInterface $user, mixed ...$arguments): bool`
- `allows()` and `denies()` are the check methods; `authorize()` throws on denial
- The Gate gets the current user from AuthManager's default guard
- Patterns to follow: Constructor injection, readonly where appropriate

## Requirements (Test Descriptions)
- [ ] `it defines abilities with closures via define method`
- [ ] `it checks if an ability is allowed via allows method`
- [ ] `it checks if an ability is denied via denies method`
- [ ] `it passes the current user to ability closures`
- [ ] `it passes additional arguments to ability closures`
- [ ] `it returns false for undefined abilities`
- [ ] `it throws AuthorizationException from authorize when denied`
- [ ] `it returns true from authorize when allowed`
- [ ] `it handles guest users by passing null to closures`
- [ ] `it allows overwriting previously defined abilities`

## Acceptance Criteria
- All requirements have passing tests
- Gate resolves user from AuthManager
- Clean separation between GateInterface (contract) and Gate (implementation)
- No final keyword on Gate class

## Implementation Notes
(Left blank - filled in by programmer during implementation)
