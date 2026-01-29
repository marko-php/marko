# Task 008: Update Mail Package Config

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
Update MailConfig class to remove fallback parameters and ensure mail.php config file has all required values.

## Context
- Related files: `packages/mail/src/Config/MailConfig.php`, `packages/mail/config/mail.php`
- Check all getString calls and remove fallback parameters
- Ensure config file defines: driver, from.address, from.name

## Requirements (Test Descriptions)
- [ ] `it reads driver from config without fallback`
- [ ] `it reads from address from config without fallback`
- [ ] `it reads from name from config without fallback`
- [ ] `config file contains all required keys with defaults`

## Acceptance Criteria
- All requirements have passing tests
- MailConfig has no fallback parameters
- mail.php config file has all values
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
