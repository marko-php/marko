# Task 005: Update PluginProxy to Call Correct Plugin Method Name

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Verify and update `PluginProxy::__call()` to use the plugin method name returned by the registry (which may now differ from the target method name when `method:` param is used). The proxy already uses `$beforeMethod['method']` and `$afterMethod['method']` for invocation, so the main change is ensuring this still works correctly with the new data shape from the registry.

## Context
- Related files: `packages/core/src/Plugin/PluginProxy.php`
- Current behavior: `$plugin->{$beforeMethod['method']}(...$arguments)` where `method` was e.g. `'beforeDoAction'`
- New behavior: same call pattern, but `method` is now e.g. `'doAction'` (standard) or `'validateInput'` (explicit method param)
- The proxy code itself may not need changes if the registry returns the correct `method` value — but this task validates that end-to-end

## Requirements (Test Descriptions)
- [ ] `it calls plugin method by name returned from registry`
- [ ] `it calls correct plugin method when method param differs from target`

## Acceptance Criteria
- All requirements have passing tests
- Proxy correctly invokes plugin methods regardless of naming convention used
- Code follows code standards

## Implementation Notes
This may be a validation-only task if `PluginProxy` already works with the new registry output. The key is writing tests that prove both standard and `method:` param invocation paths work through the full proxy stack.
