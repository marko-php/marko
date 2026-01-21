# Task 010: Integration Tests

## Status
pending

## Depends On
009

## Description
Create comprehensive integration tests that verify the complete config system works end-to-end.

## Requirements
- [ ] Create Feature test for full config lifecycle:
  - Create temporary module structure with config files
  - Run config discovery
  - Verify merged config is correct
  - Verify dot notation access works
  - Verify scoped access works
- [ ] Test environment variable integration:
  - Config file uses `$_ENV['KEY'] ?? 'default'`
  - Set env var and verify config picks it up
  - Unset env var and verify default is used
- [ ] Test error scenarios:
  - Missing required config throws ConfigNotFoundException with helpful message
  - Invalid PHP syntax in config file throws ConfigLoadException
  - Config file returns non-array throws ConfigLoadException
- [ ] Test multi-tenant scenario:
  - Config with default and scoped values
  - Verify scope resolution works correctly
  - Verify withScope() creates properly scoped instance
- [ ] Verify all tests pass with `./vendor/bin/pest packages/config/tests/ --parallel`

## Implementation Notes
<!-- Notes added during implementation -->
