# Task 004: Exceptions

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the package's exception classes: a base `PageCacheException` (extends `MarkoException` with the standard message/context/suggestion shape) and `NoDriverException` for when the interface is requested but no driver is bound.

## Context
- Related files:
  - `packages/cache/src/Exceptions/CacheException.php` (template — extends Exception with context/suggestion accessors)
  - `packages/cache/src/Exceptions/NoDriverException.php` (template for the no-driver case)
  - `.claude/code-standards.md` — Exception Standards (Loud Errors)
- Patterns to follow:
  - Extend the cache package's pattern: base exception with named parameters (`message`, `context`, `suggestion`)
  - Static factory methods for common cases
  - Always use named parameters when instantiating

## Requirements (Test Descriptions)
- [ ] `it constructs a base PageCacheException with message, context, and suggestion`
- [ ] `it exposes context and suggestion via getter methods`
- [ ] `it produces a NoDriverException via static factory with helpful message and suggestion`
- [ ] `it inherits NoDriverException from PageCacheException`

## Acceptance Criteria
- `src/Exceptions/PageCacheException.php` exists with `getContext()` and `getSuggestion()` accessors
- `src/Exceptions/NoDriverException.php` extends `PageCacheException` and has a static factory like `noBinding(): self` returning a useful suggestion (e.g., "Install marko/page-cache-file or another driver")
- Tests in `tests/Unit/Exceptions/`
- Strict types declared
- All `@throws` propagated from constructors documented

## Implementation Notes
(Left blank — filled in by programmer during implementation)
