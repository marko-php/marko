# Task 001: Core Implementation — PluginProxy Argument Modification

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Modify PluginProxy to support argument modification when a before plugin returns an array. Add comprehensive test coverage for the new behavior while ensuring all existing behavior remains unchanged.

## Context
- Primary file to modify: `packages/core/src/Plugin/PluginProxy.php` (lines 38-46)
- Test file: `packages/core/tests/Unit/Plugin/PluginInterceptorTest.php`
- The change is ~3 lines in the before plugin loop: check `is_array($result)` before the existing `!== null` check
- Current behavior: return null = pass through, return non-null = short-circuit
- New behavior: return null = pass through, return array = modify arguments, return non-null non-array = short-circuit

### Current code in PluginProxy __call():
```php
foreach ($beforeMethods as $beforeMethod) {
    $plugin = $this->container->get($beforeMethod['pluginClass']);
    $result = $plugin->{$beforeMethod['method']}(...$arguments);

    // Short-circuit if before plugin returns non-null
    if ($result !== null) {
        return $result;
    }
}
```

### Target code:
```php
foreach ($beforeMethods as $beforeMethod) {
    $plugin = $this->container->get($beforeMethod['pluginClass']);
    $result = $plugin->{$beforeMethod['method']}(...$arguments);

    if (is_array($result)) {
        // Validate argument count matches target method
        $expectedCount = (new \ReflectionMethod($this->target, $method))->getNumberOfParameters();
        if (count($result) !== $expectedCount) {
            throw new PluginArgumentCountException(
                pluginClass: $beforeMethod['pluginClass'],
                targetClass: $this->targetClass,
                targetMethod: $method,
                expectedCount: $expectedCount,
                actualCount: count($result),
            );
        }
        $arguments = $result;
    } elseif ($result !== null) {
        return $result;
    }
}
```

### New exception class:
Create `packages/core/src/Plugin/PluginArgumentCountException.php` extending `MarkoException`. The message should be helpful:
> Plugin "App\MyPlugin" returned 3 arguments for "PaymentService::charge()", which expects 2. Before plugins that modify arguments must return an array matching the target method's parameter count.

Use MarkoException's `context` and `suggestion` fields:
- **context**: Which plugin returned the wrong count
- **suggestion**: "Return an array with exactly N elements matching the parameters of TargetClass::method()"

## Requirements (Test Descriptions)
- [ ] `it modifies arguments when before plugin returns an array`
- [ ] `it passes modified arguments to the target method`
- [ ] `it chains argument modifications through multiple before plugins`
- [ ] `it passes modified arguments to after plugins` (after plugins receive modified args via `...$arguments`, not the originals)
- [ ] `it still short-circuits when before plugin returns non-null non-array`
- [ ] `it still passes through when before plugin returns null`
- [ ] `it throws PluginArgumentCountException when before plugin returns empty array and target has required params`
- [ ] `it throws PluginArgumentCountException when before plugin returns array with wrong argument count`
- [ ] `it includes plugin class name, target method, expected and actual counts in the exception message`

## Acceptance Criteria
- All new tests pass
- All existing tests still pass (no regressions)
- `./vendor/bin/pest --parallel` passes
- `./vendor/bin/php-cs-fixer fix` reports no issues
- Code follows strict_types, constructor promotion conventions

## Implementation Notes
(Left blank - filled in by programmer during implementation)
