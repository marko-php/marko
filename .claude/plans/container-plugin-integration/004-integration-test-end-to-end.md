# Task 004: Integration Test — End-to-End Plugin Interception via Container

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Create integration tests that prove the full plugin lifecycle works through the container: register plugins in PluginRegistry, resolve a target class from Container, call a method, and verify before/after plugins fire. These tests validate that the system works as a whole, not just individual pieces.

## Context
- Related files: `packages/core/tests/Unit/Container/ContainerTest.php` (add tests here alongside existing container tests)
- Patterns to follow: Existing `PluginInterceptorTest.php` fixtures and `ContainerTest.php` style
- Tests should manually wire components: create Container, create PluginRegistry, create PluginInterceptor (with Container + Registry), call `$container->setPluginInterceptor($interceptor)`, register plugins in the registry, then resolve and call methods
- Do NOT rely on Application bootstrap — these are unit/integration tests of the Container+Plugin wiring
- Use `describe('plugin interception')` block to group these tests
- For the preference + plugin test: register a preference (`InterfaceX` -> `ConcreteY`), register a plugin targeting `ConcreteY`, resolve `InterfaceX` from container, call a method, verify plugin fires

## Requirements (Test Descriptions)
- [ ] `it fires before plugin when calling method on container-resolved object`
- [ ] `it fires after plugin when calling method on container-resolved object`
- [ ] `it passes modified arguments from before plugin to target method`
- [ ] `it passes modified result from after plugin back to caller`
- [ ] `it fires plugins on preference-resolved objects`
- [ ] `it returns same proxied singleton on repeated resolves`

## Acceptance Criteria
- All requirements have passing tests
- Tests demonstrate real end-to-end behavior (not mocked)
- Code follows code standards
