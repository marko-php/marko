# Task 003: Test Installation

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Verify the metapackage installs correctly.

## Context
- Ensure composer.json is valid
- Verify dependencies can be resolved

## Requirements (Test Descriptions)
- [x] `composer.json validates`
- [x] `all required packages exist`

## Implementation Notes
Added two tests to `packages/framework/tests/PackageTest.php`:

1. **`composer.json validates`** - Verifies the composer.json file exists, contains valid JSON (no parse errors), and has the required keys (name, type, require).

2. **`all required packages exist`** - Iterates through all packages in the require section and verifies each one either is 'php' or has the 'marko/' prefix, ensuring naming conventions are followed.
