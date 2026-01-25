# Task 012: Verification Token Entity

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the VerificationToken entity for comment email verification. Stores tokens with associated email, comment, and expiration. Also stores browser tokens for cookie-based auto-approval.

## Context
- Related files: `packages/blog/src/Entity/VerificationToken.php`
- Patterns to follow: Standard entity pattern
- Two token types: email verification tokens and browser tokens

## Requirements (Test Descriptions)
- [ ] `it creates token with random secure value`
- [ ] `it associates token with email address`
- [ ] `it associates token with comment_id for email verification`
- [ ] `it has type field distinguishing email vs browser tokens`
- [ ] `it has created_at timestamp`
- [ ] `it has expires_at timestamp`
- [ ] `it checks if token is expired`
- [ ] `it finds token by value`
- [ ] `it finds browser token by email`
- [ ] `it deletes expired tokens`

## Acceptance Criteria
- All requirements have passing tests
- VerificationTokenInterface defined for Preference swapping
- VerificationToken entity with proper attributes
- VerificationTokenRepositoryInterface defined
- VerificationTokenRepository implements interface
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
