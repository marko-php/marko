# Task 012: errors-simple Package Setup

**Status**: completed
**Depends on**: 005, 010, 011
**Retry count**: 0

## Description
Set up the `marko/errors-simple` package with proper composer.json, module.php, and directory structure. This package depends on `marko/errors` and provides the reliable fallback implementation.

## Context
- Related files: `packages/errors-simple/composer.json`, `packages/errors-simple/module.php`
- Patterns to follow: Existing packages and driver pattern
- Must bind SimpleErrorHandler to ErrorHandlerInterface
- Error handler should auto-register when module boots (no special bootstrap code)

## Requirements (Test Descriptions)
- [ ] `it has valid composer.json with name marko/errors-simple`
- [ ] `it requires php 8.5 or higher`
- [ ] `it requires marko/core`
- [ ] `it requires marko/errors`
- [ ] `it has no other dependencies`
- [ ] `it has PSR-4 autoloading for Marko\\Errors\\Simple namespace`
- [ ] `it has module.php that is enabled by default`
- [ ] `it binds SimpleErrorHandler to ErrorHandlerInterface in module.php`
- [ ] `it auto-registers error handler via module boot hook`
- [ ] `it exports SimpleErrorHandler`
- [ ] `it exports TextFormatter`
- [ ] `it exports BasicHtmlFormatter`
- [ ] `it exports CodeSnippetExtractor`
- [ ] `it exports Environment`

## Acceptance Criteria
- All requirements have passing tests
- Package installs correctly with composer
- Binding is registered correctly
- Error handler registers automatically when module loads
- Code follows project standards
