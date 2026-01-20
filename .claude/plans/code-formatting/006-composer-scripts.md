# Task 006: Composer Scripts

**Status**: completed
**Depends on**: 001, 002, 003, 004
**Retry count**: 0

## Description
Add composer scripts to composer.json for convenient manual execution of code formatting tools. These provide easy commands for checking, fixing, and modernizing code.

## Context
- Location: `/composer.json` (update existing file from task 001)
- Scripts provide CLI shortcuts for development workflow

## Requirements (Test Descriptions)
- [ ] `it adds cs:check script that runs phpcs validation`
- [ ] `it adds cs:fix script that runs php-cs-fixer then phpcbf`
- [ ] `it adds rector script that runs rector code modernization`
- [ ] `composer cs:check executes without errors on clean codebase`
- [ ] `composer cs:fix executes without errors`
- [ ] `composer rector executes without errors`

## Acceptance Criteria
- All requirements have passing tests
- Scripts are documented in composer.json
- Commands work from project root

## Scripts to Add
```json
{
    "scripts": {
        "cs:check": "phpcs --standard=phpcs.xml",
        "cs:fix": [
            "php-cs-fixer fix --config=.php-cs-fixer.php",
            "phpcbf --standard=phpcs.xml || true"
        ],
        "rector": "rector process"
    }
}
```

## Usage
```bash
# Check for violations (no auto-fix)
composer cs:check

# Auto-fix all fixable violations
composer cs:fix

# Modernize code to latest PHP standards
composer rector
```

## Implementation Notes
(Left blank - filled in by programmer during implementation)
