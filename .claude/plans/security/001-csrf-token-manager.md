# Task 001: CsrfTokenManager -- Token Generation, Storage, Validation

**Status**: done
**Depends on**: none
**Retry count**: 0

## Description
Create the security package scaffolding along with CsrfTokenManager, SecurityException, CsrfTokenMismatchException, and the Contracts/CsrfTokenManagerInterface. The token manager generates cryptographically random tokens using EncryptorInterface, stores them in the session via SessionInterface, and validates submitted tokens against the stored value.

## Context
- Namespace: `Marko\Security`
- Package location: `packages/security/`
- CsrfTokenManager generates a token by encrypting a random 32-byte value via EncryptorInterface
- Token stored in session under key `_csrf_token`
- `get()` returns existing token or generates a new one (lazy generation)
- `validate(string $token)` compares submitted token to stored token using `hash_equals` for timing-safe comparison
- `regenerate()` forces generation of a new token (e.g., after login)
- SecurityException extends Exception with three-part pattern (message, context, suggestion)
- CsrfTokenMismatchException extends SecurityException with static factory methods

## Requirements (Test Descriptions)
- [ ] `it defines CsrfTokenManagerInterface with get validate and regenerate methods`
- [ ] `it generates a token and stores it in session`
- [ ] `it returns existing token from session on subsequent calls`
- [ ] `it validates correct token successfully`
- [ ] `it rejects invalid token`
- [ ] `it regenerates token replacing the previous one`
- [ ] `it creates CsrfTokenMismatchException with three-part error pattern`

## Acceptance Criteria
- CsrfTokenManagerInterface defined in Contracts/
- CsrfTokenManager implements the interface
- Token generation uses EncryptorInterface (encrypt random bytes, producing a unique string)
- Token comparison uses hash_equals (timing-safe)
- SecurityException has message, context, suggestion with getContext()/getSuggestion() accessors
- CsrfTokenMismatchException::invalidToken() static factory
- All files have strict_types=1
- Tests use anonymous class stubs for SessionInterface and EncryptorInterface

## Implementation Notes

