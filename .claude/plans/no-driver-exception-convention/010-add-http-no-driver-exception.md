# Task 010: Add NoDriverException to HTTP Package

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Create a `NoDriverException` in the HTTP package following the standard pattern.

## Context
- Related files:
  - `packages/http/src/Exceptions/HttpException.php` (extends `Exception`)
- Base exception: `HttpException` extends `Exception`, so `NoDriverException` should extend `MarkoException` directly
- Driver packages: `marko/http-guzzle`

## Requirements (Test Descriptions)
- [x] `it has DRIVER_PACKAGES constant listing marko/http-guzzle`
- [x] `it provides suggestion with composer require command`
- [x] `it includes context about resolving HTTP client interfaces`
- [x] `it extends MarkoException`

## Acceptance Criteria
- All requirements have passing tests
- Follows the standard NoDriverException pattern

## Implementation Notes
Create new file at `packages/http/src/Exceptions/NoDriverException.php`.
