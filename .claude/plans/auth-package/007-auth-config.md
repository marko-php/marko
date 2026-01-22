# Task 007: AuthConfig

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the AuthConfig class that loads configuration from config/auth.php.

## Context
- Related files: packages/session/src/Config/SessionConfig.php (pattern to follow)
- Configuration includes guards, providers, password settings, remember token settings

## Requirements (Test Descriptions)
- [x] `it creates AuthConfig class`
- [x] `it loads default guard name`
- [x] `it loads default provider name`
- [x] `it loads guards configuration array`
- [x] `it loads providers configuration array`
- [x] `it loads password hasher settings`
- [x] `it loads remember token settings`
- [x] `it provides getter for bcrypt cost`
- [x] `it provides default configuration file`

## Acceptance Criteria
- All requirements have passing tests
- Config follows same pattern as SessionConfig
- Creates config/auth.php default file

## Implementation Notes
(Left blank - filled in by programmer during implementation)
