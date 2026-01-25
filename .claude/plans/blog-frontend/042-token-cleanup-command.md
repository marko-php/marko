# Task 042: Blog Cleanup Command

**Status**: completed
**Depends on**: 012
**Retry count**: 0

## Description
Create a CLI command for blog module housekeeping: expired verification tokens. Intended to run daily via cron to maintain database hygiene.

**Note:** Rate limit cache entries are NOT cleaned up by this command. They use TTL-based automatic expiry via CacheInterface - no manual cleanup needed.

## Context
- Related files: `packages/blog/src/Commands/CleanupCommand.php`
- Patterns to follow: Marko CLI command with `#[Command]` attribute
- Command: `blog:cleanup`
- Handles database cleanup (verification tokens) only

## Requirements (Test Descriptions)
- [x] `it is registered as blog:cleanup command`
- [x] `it deletes email verification tokens older than configured expiry`
- [x] `it deletes browser tokens older than configured cookie days`
- [x] `it reports count of deleted email verification tokens`
- [x] `it reports count of deleted browser tokens`
- [x] `it handles case when nothing to clean up`
- [x] `it provides verbose output option`
- [x] `it returns success exit code on completion`

## Acceptance Criteria
- All requirements have passing tests
- Command registered and discoverable
- Uses VerificationTokenRepositoryInterface for token deletion
- Uses BlogConfigInterface for expiry settings
- Code follows Marko standards

## Implementation Notes
- Created `CleanupCommand` at `/packages/blog/src/Commands/CleanupCommand.php`
- Extended `TokenRepositoryInterface` with `deleteExpiredEmailTokens()` and `deleteExpiredBrowserTokens()` methods
- Updated `MockTokenRepository` in `CommentVerificationServiceTest.php` to implement new interface methods
- Command uses `BlogConfigInterface` for expiry settings (`getVerificationTokenExpiryDays()` and `getVerificationCookieDays()`)
- Verbose mode shows configuration values before cleanup
- Note: Uses `TokenRepositoryInterface` (not `VerificationTokenRepositoryInterface` as mentioned in acceptance criteria - this is the actual interface name in the codebase)
