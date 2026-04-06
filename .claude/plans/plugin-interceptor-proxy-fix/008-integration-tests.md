# Task 008: Integration Tests

**Status**: pending
**Depends on**: 007
**Retry count**: 0

## Description
Add integration tests that verify the full plugin interception flow end-to-end: from plugin registration through container resolution to method invocation. These tests exercise the complete stack (Container + PluginRegistry + PluginInterceptor + InterceptorClassGenerator + PluginInterception trait) together, covering the exact scenario from the bug report.

## Context
- Related files:
  - `packages/core/tests/Unit/Plugin/` — existing plugin tests for reference
  - `packages/core/src/Container/Container.php` — container under test
  - `packages/core/src/Plugin/` — all plugin classes
- New test file: `packages/core/tests/Feature/Plugin/PluginInterceptionIntegrationTest.php` (using `Feature/` directory per project convention — no `Integration/` directory exists)
- These tests create a full Container with PluginInterceptor, register bindings and plugins, then resolve services and verify plugin execution
- Test interfaces and classes should be defined as fixtures in the test file

## Requirements (Test Descriptions)

- [ ] `it intercepts interface method calls when plugin targets the interface via closure binding`
- [ ] `it intercepts interface method calls when plugin targets the interface via preference resolution`
- [ ] `it returns object that passes instanceof check for target interface`
- [ ] `it executes before plugin then target method then after plugin in correct order`
- [ ] `it short-circuits target method when before plugin returns non-null`
- [ ] `it modifies arguments via before plugin array return`
- [ ] `it chains after plugin results`
- [ ] `it works when plugin targets concrete class that is not readonly`
- [ ] `it throws PluginException when plugin targets readonly concrete class directly`
- [ ] `it injects intercepted service into another service constructor without TypeError`
- [ ] `it works with singleton services that have plugins`
- [ ] `it exposes original target via getPluginTarget on intercepted instance`

## Acceptance Criteria
- All requirements have passing tests
- Tests exercise the full stack end-to-end
- The exact scenario from the bug report (HasherInterface plugin → controller injection) is covered
- Code follows code standards

## Implementation Notes
(Left blank - filled in by programmer during implementation)
