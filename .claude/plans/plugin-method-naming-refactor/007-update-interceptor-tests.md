# Task 007: Update PluginInterceptorTest Fixtures and Tests

**Status**: completed
**Depends on**: 002, 005
**Retry count**: 0

## Description
Update all test fixtures and test cases in `PluginInterceptorTest.php` to use the new naming convention. Plugin methods should be named to match the target method (no `before`/`after` prefix). `PluginDefinition` constructors in tests must use the new array structure. Add new test for `method:` param usage through the full interceptor stack.

## Context
- Related files: `packages/core/tests/Unit/Plugin/PluginInterceptorTest.php` (718 lines)
- 15 fixture classes need method renaming (e.g., `beforeDoAction` -> `doAction`, `afterDoAction` -> `doAction`)
- 11 test cases need `PluginDefinition` constructor updates
- All `PluginDefinition` constructors in tests use old format: `beforeMethods: ['beforeDoAction' => 10]` -> new format: `beforeMethods: ['doAction' => ['pluginMethod' => 'doAction', 'sortOrder' => 10]]`
- Add test for `method:` param path through proxy

## Critical: PluginWithDependency Name Collision

The `PluginWithDependency` fixture class (line 614-640) currently has BOTH `beforeSave()` and `afterSave()` targeting the same method `save`. After the refactor, both would need to be named `save()` -- but PHP does not allow two methods with the same name in one class. This fixture MUST use the `method:` param escape hatch:

```php
readonly class PluginWithDependency
{
    public function __construct(
        private PluginLoggerDependency $logger,
    ) {}

    #[Before(method: 'save')]
    public function logBeforeSave(string $data): ?string
    {
        $this->logger->log("About to save: $data");
        DependencyInjectionService::$callLog[] = 'PluginWithDependency::logBeforeSave';
        return null;
    }

    #[After(method: 'save')]
    public function logAfterSave(mixed $result, string $data): mixed
    {
        $this->logger->log("Saved successfully: $data");
        DependencyInjectionService::$callLog[] = 'PluginWithDependency::logAfterSave';
        return $result;
    }
}
```

The PluginDefinition for this fixture must use the `method:` param format:
```php
beforeMethods: ['save' => ['pluginMethod' => 'logBeforeSave', 'sortOrder' => 10]],
afterMethods: ['save' => ['pluginMethod' => 'logAfterSave', 'sortOrder' => 10]],
```

The test assertion call log strings must also be updated to match the new method names.

## Requirements (Test Descriptions)
- [ ] `it executes before plugins with method names matching target method`
- [ ] `it executes after plugins with method names matching target method`
- [ ] `it executes plugins using explicit method param with different method names`
- [ ] `it passes method arguments to before plugins with new naming`
- [ ] `it short-circuits when before plugin returns non-null with new naming`
- [ ] `it passes result and arguments to after plugins with new naming`
- [ ] `it chains modified results through after plugins with new naming`

## Acceptance Criteria
- All existing tests pass with updated fixtures
- New test for `method:` param works end-to-end
- All fixture methods use new naming convention
- `PluginWithDependency` uses `method:` param pattern (both before and after on same target)
- Code follows code standards

## Implementation Notes
This is the largest test update task. Changes required:

**Fixture method renames** (method name changes + call log string updates in method bodies):
- `FirstBeforePlugin::beforeDoAction` -> `FirstBeforePlugin::doAction`
- `SecondBeforePlugin::beforeDoAction` -> `SecondBeforePlugin::doAction`
- `ArgLoggingPlugin::beforeProcess` -> `ArgLoggingPlugin::process`
- `ShortCircuitPlugin::beforeDoAction` -> `ShortCircuitPlugin::doAction`
- `SkippedPlugin::beforeDoAction` -> `SkippedPlugin::doAction`
- `PassThroughPluginA::beforeDoAction` -> `PassThroughPluginA::doAction`
- `PassThroughPluginB::beforeDoAction` -> `PassThroughPluginB::doAction`
- `FirstAfterPlugin::afterDoAction` -> `FirstAfterPlugin::doAction`
- `SecondAfterPlugin::afterDoAction` -> `SecondAfterPlugin::doAction`
- `AfterArgInspectorPlugin::afterCalculate` -> `AfterArgInspectorPlugin::calculate`
- `DoublerPlugin::afterGetValue` -> `DoublerPlugin::getValue`
- `AdderPlugin::afterGetValue` -> `AdderPlugin::getValue`
- `CompleteFlowBeforePlugin::beforeProcess` -> `CompleteFlowBeforePlugin::process`
- `CompleteFlowAfterPlugin::afterProcess` -> `CompleteFlowAfterPlugin::process`
- `ProxyCheckServicePlugin::beforeDoSomething` -> `ProxyCheckServicePlugin::doSomething`
- `PluginWithDependency::beforeSave` -> `PluginWithDependency::logBeforeSave` (with `#[Before(method: 'save')]`)
- `PluginWithDependency::afterSave` -> `PluginWithDependency::logAfterSave` (with `#[After(method: 'save')]`)

**Test assertion call log strings**: Every `expect()` assertion that checks `$callLog` arrays contains the old method names (e.g., `'FirstBeforePlugin::beforeDoAction'`). These must ALL be updated to match the new method names (e.g., `'FirstBeforePlugin::doAction'`). There are approximately 30+ such strings across 11 test cases.
