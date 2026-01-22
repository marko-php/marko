# Task 007: AuthConfig

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Create the AuthConfig class that loads configuration from config/auth.php.

## Context
- Related files: packages/session/src/Config/SessionConfig.php (pattern to follow)
- Configuration includes guards, providers, password settings, remember token settings

## Requirements (Test Descriptions)
- [ ] `it creates AuthConfig class`
- [ ] `it loads default guard name`
- [ ] `it loads default provider name`
- [ ] `it loads guards configuration array`
- [ ] `it loads providers configuration array`
- [ ] `it loads password hasher settings`
- [ ] `it loads remember token settings`
- [ ] `it provides getter for bcrypt cost`
- [ ] `it provides default configuration file`

## Acceptance Criteria
- All requirements have passing tests
- Config follows same pattern as SessionConfig
- Creates config/auth.php default file

## Implementation Notes
(Left blank - filled in by programmer during implementation)
