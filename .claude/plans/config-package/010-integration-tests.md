# Task 010: Integration Tests

## Status
completed

## Depends On
009

## Description
Create comprehensive integration tests that verify the complete config system works end-to-end.

## Requirements
- [x] Create Feature test for full config lifecycle:
  - Create temporary module structure with config files
  - Run config discovery
  - Verify merged config is correct
  - Verify dot notation access works
  - Verify scoped access works
- [x] Test environment variable integration:
  - Config file uses `$_ENV['KEY'] ?? 'default'`
  - Set env var and verify config picks it up
  - Unset env var and verify default is used
- [x] Test error scenarios:
  - Missing required config throws ConfigNotFoundException with helpful message
  - Invalid PHP syntax in config file throws ConfigLoadException
  - Config file returns non-array throws ConfigLoadException
- [x] Test multi-tenant scenario:
  - Config with default and scoped values
  - Verify scope resolution works correctly
  - Verify withScope() creates properly scoped instance
- [x] Verify all tests pass with `./vendor/bin/pest packages/config/tests/ --parallel`

## Implementation Notes
Added 8 new integration tests to `packages/config/tests/Feature/ConfigIntegrationTest.php`:

1. `it('config file uses environment variable when set')` - Tests that config files can use `$_ENV` variables
2. `it('config file uses default when environment variable is not set')` - Tests fallback to defaults when env vars are unset
3. `it('throws ConfigNotFoundException with helpful message when required config is missing')` - Tests error handling for missing config keys
4. `it('throws ConfigLoadException when config file has PHP syntax error')` - Tests error handling for invalid PHP syntax
5. `it('throws ConfigLoadException when config file returns non-array')` - Tests error handling for non-array returns
6. `it('full config lifecycle with temporary module structure')` - Comprehensive end-to-end test with temp directories
7. `it('multi-tenant scenario with default and scoped values')` - Tests complex multi-tenant config structures
8. `it('withScope creates properly scoped instance for multi-tenant access')` - Tests the withScope() method for creating scoped instances

All tests use proper cleanup with try/finally blocks to ensure temporary files and directories are removed. Total package tests: 101 passed (330 assertions).
