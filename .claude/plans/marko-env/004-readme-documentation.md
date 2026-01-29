# Task 004: Create Comprehensive README

**Status**: pending
**Depends on**: 001, 002
**Retry count**: 0

## Description
Create a comprehensive README.md that explains the package philosophy (config files as source of truth), usage patterns, best practices, what belongs in .env vs config files, and important warnings about not committing .env to VCS.

## Context
- Related files: `packages/env/README.md` (new file)
- Patterns to follow: Clear documentation with examples
- Key philosophy: Config files are source of truth, .env provides overrides
- Reference design document: `.claude/plans/marko-env-package.md`

## Requirements (Test Descriptions)
- [ ] `it has installation section with composer require`
- [ ] `it explains config files as source of truth philosophy`
- [ ] `it contrasts Marko approach with Laravel approach`
- [ ] `it shows basic env() usage with defaults`
- [ ] `it documents type coercion for true/false/null/empty`
- [ ] `it explains what belongs in .env vs config files with table`
- [ ] `it shows example config file using env() with defaults`
- [ ] `it shows minimal .env.example template`
- [ ] `it warns about not committing .env to version control`
- [ ] `it explains apps work without .env file`
- [ ] `it documents EnvLoader class for advanced usage`
- [ ] `it includes .gitignore recommendation for .env`

## Acceptance Criteria
- README is comprehensive and well-organized
- Examples are clear and copy-pasteable
- Philosophy section explains the "why" not just "how"
- Warnings about security (not committing .env) are prominent

## Implementation Notes
(Left blank - filled in by programmer during implementation)
