# Task 001: AuthorizableInterface and AuthorizationException

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the foundational contracts and exception for the authorization package. `AuthorizableInterface` defines what a user must implement to support authorization checks. Enhance `AuthorizationException` beyond what exists in marko/auth to support the richer authorization context (ability, resource type, policy class).

## Context
- Related files: `packages/auth/src/Exceptions/AuthorizationException.php` (existing, minimal), `packages/auth/src/Exceptions/AuthException.php` (pattern reference)
- The authorization package gets its own exception class (not reusing auth's) since it's a separate package
- Patterns to follow: Three-part exceptions (message, context, suggestion), constructor property promotion, strict types
- Package location: `packages/authorization/`
- Namespace: `Marko\Authorization`

## Requirements (Test Descriptions)
- [ ] `it defines AuthorizableInterface with getAuthIdentifier method`
- [ ] `it defines AuthorizableInterface with getCan method returning Gate access`
- [ ] `it creates AuthorizationException with ability and resource context`
- [ ] `it creates AuthorizationException via forbidden factory method`
- [ ] `it creates AuthorizationException via missingPolicy factory method`
- [ ] `it provides context and suggestion on AuthorizationException`

## Acceptance Criteria
- All requirements have passing tests
- `AuthorizableInterface` extends `AuthenticatableInterface` from marko/auth
- Exception follows three-part pattern (message, context, suggestion)
- Code follows project standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
