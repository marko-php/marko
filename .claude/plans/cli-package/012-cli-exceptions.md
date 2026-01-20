# Task 012: CLI Exceptions

**Status**: pending
**Depends on**: 010
**Retry count**: 0

## Description
Create CLI-specific exceptions that provide helpful error messages. Following the "loud errors" philosophy with clear descriptions and suggestions for resolution.

## Context
- Directory: `packages/cli/src/Exceptions/`
- Pattern: Follow MarkoException pattern from core
- Focus: User-friendly messages for CLI context

## Requirements (Test Descriptions)
- [ ] `it creates ProjectNotFoundException with helpful message`
- [ ] `it includes current directory in ProjectNotFoundException message`
- [ ] `it suggests running from project directory in ProjectNotFoundException`
- [ ] `it creates BootstrapException for boot failures`
- [ ] `it includes cause in BootstrapException message`
- [ ] `it creates CommandNotFoundException with helpful message`
- [ ] `it suggests running list command in CommandNotFoundException`
- [ ] `all exceptions extend base CliException`

## Acceptance Criteria
- All requirements have passing tests
- Messages follow loud errors philosophy
- Each exception includes resolution suggestion
- Consistent exception hierarchy
- Code follows code standards
