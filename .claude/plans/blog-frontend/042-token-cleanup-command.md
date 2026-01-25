# Task 042: Blog Cleanup Command

**Status**: pending
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
- [ ] `it is registered as blog:cleanup command`
- [ ] `it deletes email verification tokens older than configured expiry`
- [ ] `it deletes browser tokens older than configured cookie days`
- [ ] `it reports count of deleted email verification tokens`
- [ ] `it reports count of deleted browser tokens`
- [ ] `it handles case when nothing to clean up`
- [ ] `it provides verbose output option`
- [ ] `it returns success exit code on completion`

## Acceptance Criteria
- All requirements have passing tests
- Command registered and discoverable
- Uses VerificationTokenRepositoryInterface for token deletion
- Uses BlogConfigInterface for expiry settings
- Code follows Marko standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
