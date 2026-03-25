# Task 008: Update PluginDiscoveryTest, PluginRegistryTest, and ApplicationTest

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Update test fixtures and assertions in `PluginDiscoveryTest.php`, `PluginRegistryTest.php`, and `ApplicationTest.php` to use the new naming convention and `PluginDefinition` array structure.

## Context
- Related files:
  - `packages/core/tests/Unit/Plugin/PluginDiscoveryTest.php` (208 lines)
  - `packages/core/tests/Unit/Plugin/PluginRegistryTest.php` (159 lines)
  - `packages/core/tests/Unit/ApplicationTest.php` (plugin discovery test around line 348-388)
- **PluginDiscoveryTest**: fixture `TargetServicePlugin` has `beforeDoSomething`/`afterDoSomething` -- rename to `doSomething` for both. Test assertions check for `'beforeDoSomething'` key in `$definition->beforeMethods` -- update to check for `'doSomething'` key with new array structure. The `createPluginClass` helper does NOT auto-generate prefixed names -- it takes method names from caller-provided arrays. Update the CALLING test code to pass unprefixed names (e.g., `'getUser'` instead of `'beforeGetUser'`), not the helper itself.
- **PluginRegistryTest**: fixtures `OrderValidationPlugin`, `OrderLoggingPlugin`, `PaymentAuditPlugin`, `HighPriorityPlugin`, `MediumPriorityPlugin`, `LowPriorityPlugin` all have prefixed method names -- rename. All `PluginDefinition` constructors use old array format -- update to new format.
- **ApplicationTest**: The plugin discovery integration test (line ~348-388) generates a plugin class file with `beforeDoSomething()` method via a heredoc string. Update the generated plugin class to use the new naming convention (`doSomething()` instead of `beforeDoSomething()`).

## Requirements (Test Descriptions)
- [ ] `it discovers plugin classes in module src directories with new naming`
- [ ] `it extracts target-method-keyed before methods from plugin class`
- [ ] `it extracts target-method-keyed after methods from plugin class`
- [ ] `it collects all plugins for a given target class with new definition format`
- [ ] `it sorts plugins by sortOrder with new definition format`
- [ ] `it validates orphaned plugin methods still throws exception`
- [ ] `it discovers and registers plugins in ApplicationTest with new naming`

## Acceptance Criteria
- All existing tests pass with updated fixtures and assertions
- `createPluginClass` callers pass unprefixed method names
- ApplicationTest plugin fixture uses new naming convention
- Code follows code standards

## Implementation Notes
- The `createPluginClass` helper function itself does NOT need changes -- it writes whatever method names the caller passes in the `$beforeMethods`/`$afterMethods` arrays. Only update the test call sites.
- In ApplicationTest, the heredoc around line 348 generates a plugin class source file -- update the method name in the string from `beforeDoSomething` to `doSomething`.
- For PluginRegistryTest, the `PluginDefinition` constructors change from e.g. `beforeMethods: ['beforePlaceOrder' => 10]` to `beforeMethods: ['placeOrder' => ['pluginMethod' => 'placeOrder', 'sortOrder' => 10]]`.
