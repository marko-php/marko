# Task 002: PHP-CS-Fixer Configuration

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the PHP-CS-Fixer configuration file with the project's formatting rules. This handles automatic code style fixing including imports, array formatting, function arguments, and class structure.

## Context
- Location: `/.php-cs-fixer.php` (project root)
- Base standard: PSR-12
- Directories to scan: `packages/`, `demo/app/`, `demo/modules/`

## Requirements (Test Descriptions)
- [ ] `it uses PSR-12 as base ruleset`
- [ ] `it enforces fully qualified strict imports for classes`
- [ ] `it enforces short array syntax`
- [ ] `it enforces trailing commas in multiline arrays`
- [ ] `it enforces multiline function arguments (one per line)`
- [ ] `it enforces single blank line between methods`
- [ ] `it enforces single blank line between properties`
- [ ] `it removes unused imports`
- [ ] `it sorts imports alphabetically`
- [ ] `it enforces single quotes for strings`
- [ ] `it removes extra blank lines`
- [ ] `it removes whitespace in blank lines`
- [ ] `it allows single-line empty class bodies`
- [ ] `it scans packages directory`
- [ ] `it scans demo/app directory`
- [ ] `it scans demo/modules directory`

## Acceptance Criteria
- All requirements have passing tests
- php-cs-fixer runs without errors
- Configuration produces consistent formatting

## Files to Create
```
.php-cs-fixer.php
```

## Rules Configuration
```php
$rules = [
    '@PSR12' => true,
    'fully_qualified_strict_types' => ['import_symbols' => true],
    'global_namespace_import' => ['import_classes' => true, 'import_constants' => false, 'import_functions' => false],
    'array_syntax' => ['syntax' => 'short'],
    'trailing_comma_in_multiline' => ['elements' => ['arrays', 'match', 'parameters', 'arguments']],
    'array_indentation' => true,
    'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline', 'after_heredoc' => true],
    'class_attributes_separation' => ['elements' => ['method' => 'one', 'property' => 'one']],
    'single_line_empty_body' => true,
    'no_unused_imports' => true,
    'ordered_imports' => ['sort_algorithm' => 'alpha'],
    'single_quote' => true,
    'no_extra_blank_lines' => true,
    'no_whitespace_in_blank_line' => true,
];
```

## Implementation Notes
(Left blank - filled in by programmer during implementation)
