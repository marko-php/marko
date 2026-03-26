# Task 008: Add NoDriverException to Filesystem Package

**Status**: complete
**Depends on**: 001
**Retry count**: 0

## Description
Create a `NoDriverException` in the filesystem package following the standard pattern.

## Context
- Related files:
  - `packages/filesystem/src/Exceptions/FilesystemException.php` (extends `Exception`)
  - `packages/filesystem/src/Discovery/DriverRegistry.php` (has hardcoded `composer require` suggestion)
  - `packages/filesystem/src/Manager/FilesystemManager.php` (has driver suggestion)
- Base exception: `FilesystemException` extends `Exception`, so `NoDriverException` should extend `MarkoException` directly
- Driver packages: `marko/filesystem-local`, `marko/filesystem-s3`

## Requirements (Test Descriptions)
- [x] `it has DRIVER_PACKAGES constant listing marko/filesystem-local and marko/filesystem-s3`
- [x] `it provides suggestion with composer require commands for all driver packages`
- [x] `it includes context about resolving filesystem interfaces`
- [x] `it extends MarkoException`

## Acceptance Criteria
- All requirements have passing tests
- Follows the standard NoDriverException pattern

## Implementation Notes
Create new file at `packages/filesystem/src/Exceptions/NoDriverException.php`.
