# Task 001: Root composer.json with Dev Dependencies

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the root composer.json for the monorepo with all dev dependencies needed for code formatting tools. This establishes the project metadata and tool versions.

## Context
- Location: `/composer.json` (project root)
- This is the monorepo root, not individual package composer.json files
- PHP 8.5+ requirement
- Monorepo packages will be in packages/ directory

## Requirements (Test Descriptions)
- [ ] `it has valid composer.json at project root`
- [ ] `it requires PHP 8.5 or higher`
- [ ] `it includes friendsofphp/php-cs-fixer as dev dependency`
- [ ] `it includes squizlabs/php_codesniffer as dev dependency`
- [ ] `it includes slevomat/coding-standard as dev dependency`
- [ ] `it includes rector/rector as dev dependency`
- [ ] `it has marko/marko as package name`
- [ ] `it validates successfully with composer validate`

## Acceptance Criteria
- All requirements have passing tests
- composer.json passes `composer validate`
- Dependencies can be installed with `composer install`

## Files to Create
```
composer.json
```

## Dev Dependencies
```json
{
    "require-dev": {
        "friendsofphp/php-cs-fixer": "^3.92",
        "rector/rector": "^2.3",
        "squizlabs/php_codesniffer": "^4.0",
        "slevomat/coding-standard": "^8.26"
    }
}
```

## Implementation Notes
(Left blank - filled in by programmer during implementation)
