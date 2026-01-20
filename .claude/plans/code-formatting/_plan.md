# Plan: Code Formatting Setup

## Created
2026-01-19

## Status
completed

## Objective
Set up consistent code formatting and linting tools (php-cs-fixer, phpcs, rector) with git hooks to enforce standards automatically on every commit.

## Scope

### In Scope
- PHP-CS-Fixer configuration (PSR-12 base, multiline args, imports, etc.)
- PHPCS configuration with Slevomat rules
- Rector configuration for code modernization
- Git pre-commit hook for automatic formatting
- Composer scripts for manual formatting/checking
- Dev dependencies installation

### Out of Scope
- Business logic or architecture decisions
- Test framework setup (separate concern)
- CI/CD pipeline configuration
- IDE-specific settings

## Success Criteria
- [ ] php-cs-fixer configured with all formatting rules
- [ ] phpcs configured with Slevomat coding standard rules
- [ ] rector configured for PHP 8.5+ modernization
- [ ] Git hooks auto-format and validate on commit
- [ ] `composer cs:check`, `composer cs:fix`, `composer rector` scripts work
- [ ] All dev dependencies installed

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Root composer.json with dev dependencies | - | completed |
| 002 | PHP-CS-Fixer configuration | - | completed |
| 003 | PHPCS configuration with Slevomat | - | completed |
| 004 | Rector configuration | - | completed |
| 005 | Git pre-commit hook | 002, 003 | completed |
| 006 | Composer scripts | 001, 002, 003, 004 | completed |

## Architecture Notes

### Tools Purpose
- **php-cs-fixer**: Auto-fixes code style (formatting, imports, spacing)
- **phpcs/phpcbf**: Validates/fixes coding standard violations (Slevomat rules)
- **rector**: Modernizes code to use latest PHP features

### Directory Coverage
All tools scan: `packages/`, `demo/app/`, `demo/modules/`

### Git Hook Workflow
1. Stage PHP files
2. php-cs-fixer auto-fixes and re-stages
3. phpcbf auto-fixes remaining issues
4. phpcs validates - fails commit if unfixable violations remain

## Risks & Mitigations
- **Tool version conflicts**: Pin specific versions in composer.json
- **Performance on large commits**: Hook only processes staged files
