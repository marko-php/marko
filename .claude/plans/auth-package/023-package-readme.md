# Task 023: Package README

**Status**: completed
**Depends on**: 022
**Retry count**: 0

## Description
Create comprehensive README.md for the auth package.

## Context
- Document installation and configuration
- Show usage examples
- Explain guard types and customization
- Document events and middleware

## Requirements (Test Descriptions)
- [x] `README exists in package root`
- [x] `README includes installation instructions`
- [x] `README includes configuration examples`
- [x] `README includes usage examples`
- [x] `README documents guards`
- [x] `README documents middleware`
- [x] `README documents events`

## Acceptance Criteria
- All requirements have passing tests
- README is comprehensive
- Examples are accurate and tested

## Implementation Notes
Created comprehensive README.md for the auth package following the Package README Standards:

1. **Title + One-Liner**: Describes session/token auth with guards, events, and middleware
2. **Overview**: Explains two built-in guards and customization options
3. **Installation**: `composer require marko/auth`
4. **Configuration**: Full config/auth.php example with guards, providers, password hashing, and remember-me settings
5. **Usage**: Examples for checking auth, logging in/out, and implementing AuthenticatableInterface
6. **Guards**: Documentation for SessionGuard, TokenGuard, and custom guard implementation
7. **Middleware**: AuthMiddleware and GuestMiddleware with usage examples
8. **Events**: LoginEvent, LogoutEvent, FailedLoginEvent, PasswordResetEvent with observer examples
9. **API Reference**: Public signatures for AuthManager, GuardInterface, AuthenticatableInterface, UserProviderInterface

Test file created: `packages/auth/tests/ReadmeTest.php` with 7 tests verifying README content.
