# Devil's Advocate Review: plugin-method-naming-refactor

## Critical (Must fix before building)

### C1: PluginDefinition new array structure breaks iteration in PluginRegistry (tasks 002, 004)

The plan changes `beforeMethods`/`afterMethods` from `array<string, int>` to `array<string, array{pluginMethod: string, sortOrder: int}>`. However, `PluginRegistry::getSortedMethodsFor()` at line 97 iterates as `foreach ($plugin->$methodsProperty as $methodName => $sortOrder)` -- with the new structure, `$sortOrder` would be an array, not an int. Task 004 knows it needs to update this, but task 002 will break all existing tests immediately when it changes the PluginDefinition structure, because tasks 003, 004, 007, and 008 haven't been applied yet.

**Fix**: Task 002 must not be built in isolation -- it needs to also update the PluginDiscovery and PluginRegistry at the same time, or the test suite will be completely broken between tasks. Since this is a TDD workflow, the worker for task 002 will have no passing baseline. Merge tasks 002, 003, and 004 into a single task, or restructure so task 002 includes updating the registry iteration to use the new structure.

### C2: ApplicationTest has a plugin fixture using old naming (not covered by any task)

`packages/core/tests/Unit/ApplicationTest.php` at line 362 generates a plugin class with `beforeDoSomething()` method naming. None of tasks 007, 008, or 009 mention updating ApplicationTest. After the refactor, the ApplicationTest plugin discovery test will still generate old-style method names. While this won't break the test (it just tests discovery, not invocation), the PluginDefinition structure it creates via `parsePluginClass()` will change shape, and any assertions about the registered plugin's structure would need updating.

**Fix**: Add ApplicationTest to task 008's scope or create a note in the task about updating it.

### C3: PluginDiscovery `createPluginClass` helper in tests generates method names that callers pass in (task 008)

Task 008 says "createPluginClass helper generates method names with `before`/`after` prefix -- update to generate without prefix." But looking at the actual code (PluginDiscoveryTest.php lines 37-84), `createPluginClass()` does NOT auto-generate prefixed names -- it takes `$beforeMethods` and `$afterMethods` arrays where the caller provides the method names as keys. The helper just writes whatever name the caller passes. The callers (test cases) pass prefixed names like `'beforeGetUser' => 0`. So the fix is to update the calling test cases, not the helper function itself.

**Fix**: Correct task 008's description -- the helper doesn't need changes, only the callers do.

### C4: Task 004 depends only on 002, but needs 003's discovery changes to test properly (task 004)

Task 004 updates PluginRegistry to iterate the new PluginDefinition structure. But its tests construct PluginDefinition objects directly (not via discovery), so it only truly depends on task 002. However, the plan has task 003 depending on 001+002, and task 004 depending only on 002. This is actually correct for parallel execution -- both 003 and 004 can proceed after 002. No issue here on second analysis.

## Important (Should fix before building)

### I1: `PluginWithDependency` fixture in PluginInterceptorTest has BOTH before and after on same target (task 007)

The `PluginWithDependency` class (line 614-640) has both `beforeSave()` and `afterSave()` targeting the same method `save`. After the refactor, both methods would be renamed to `save()` -- but you can't have two methods named `save()` in the same class. This is the exact use case for the `method:` param escape hatch. Task 007 needs to explicitly call this out -- one of the methods must use `#[Before(method: 'save')]` or `#[After(method: 'save')]` with a different method name.

**Fix**: Task 007 must specify that `PluginWithDependency` needs the `method:` param pattern, e.g., `beforeSave` stays as-is but with `#[Before(method: 'save')]`, or rename to something like `logBeforeSave` with `#[Before(method: 'save')]`.

### I2: `CompleteFlowBeforePlugin` and `CompleteFlowAfterPlugin` target the same method `process` (task 007)

These are separate classes, so both can have a method named `process()`. No conflict here. But the call log strings in assertions (lines 539-543) reference `CompleteFlowBeforePlugin::beforeProcess` and `CompleteFlowAfterPlugin::afterProcess` -- these will need to change to `CompleteFlowBeforePlugin::process` and `CompleteFlowAfterPlugin::process`. Task 007 should explicitly note that assertion strings need updating.

**Fix**: Add a note to task 007 about updating call log assertion strings.

### I3: Architecture doc plugin section is sparse -- no method naming convention documented (task 009)

The architecture doc (lines 644-672) doesn't currently document the naming convention at all, just that plugins are discovered via attributes. Task 009 should add the naming convention (method name = target method name) to the architecture doc's Plugin Discovery section, not just update the existing text.

### I4: PluginInterceptorTest fixture classes all have `$callLog` strings with old names (task 007)

Every fixture class logs strings like `'FirstBeforePlugin::beforeDoAction'`, `'ShortCircuitPlugin::beforeDoAction'`, etc. After renaming methods, these log strings must also change (e.g., `'FirstBeforePlugin::doAction'`). Task 007 mentions "call log strings in test assertions" but should be more explicit that BOTH the fixture method bodies AND the test assertions need string updates. There are approximately 30+ such strings.

### I5: Task 007 is too large for a single TDD worker

Task 007 modifies 15 fixture classes, 11 test cases, all PluginDefinition constructors, all call log strings, AND adds a new test for the `method:` param path. This is a ~718-line file with changes on nearly every line. This should be flagged as a large task.

**Fix**: Add a note to task 007 acknowledging its size, or split it into two tasks (fixture updates vs. new method-param test).

### I6: No task covers the `PluginProxy` being `readonly` -- ensures no runtime state issues

`PluginProxy` is `readonly` (line 12 of PluginProxy.php). This is fine since it just delegates. No issue, just noting for completeness.

### I7: Existing `PluginAttributesTest` needs updating (task 001)

Task 001 specifies a test file `packages/core/tests/Unit/Plugin/PluginAttributesTest.php` and says "Existing attribute tests still pass (updated for new constructor signatures)." The existing tests (4 tests) create `Before`/`After` with only `sortOrder`. Adding a `method` parameter won't break these (it's optional), but the test file should be the same file that gets the new tests added. Task 001 should note that the 4 existing tests remain unchanged (not "updated") since the new param is optional.

## Minor (Nice to address)

### M1: The `method` param name on Before/After could collide with future PHP attribute features

The parameter name `method` is generic. Consider if `targetMethod` would be clearer and less likely to collide. This is a naming preference, not a bug.

### M2: No migration/deprecation path for the old naming convention

The plan notes this as a risk but the only mitigation is "document the migration path clearly." Since this is pre-1.0, a hard break is acceptable, but task 009 could include a brief "Migration from old naming" section in the docs.

### M3: `createPluginClass` helper could be enhanced to support `method:` param

The test helper in PluginDiscoveryTest generates plugin class source code. It currently doesn't support generating `#[Before(method: 'xxx')]` syntax. If future tests need this, the helper would need updating. Not blocking since task 006/008 tests can construct fixtures differently.

## Questions for the Team

### Q1: Should `PluginWithDependency` use different method names or keep old names with `method:` param?

When a single plugin class needs both before and after hooks on the same target method, what's the recommended naming pattern? Options:
- `logBeforeSave` + `#[Before(method: 'save')]` and `logAfterSave` + `#[After(method: 'save')]`
- `validateSave` + `#[Before(method: 'save')]` and `enrichSave` + `#[After(method: 'save')]`
- Keep `beforeSave`/`afterSave` names with explicit `method:` params (preserves old readability)

### Q2: Should the duplicate hook validation (task 006) also warn when two DIFFERENT plugin classes target the same method with the same sort order?

Currently, task 006 only validates within a single plugin class. Two different plugins with `#[Before]` on methods both targeting `save` with sort order 0 would silently have non-deterministic ordering. Is this worth a warning?
