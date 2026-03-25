# Devil's Advocate Review: before-plugin-argument-modification

## Critical (Must fix before building)

### C1. `core.md` example return type must change (Task 002)

The `PaymentValidationPlugin::charge()` currently has return type `?float`. The plan says to change the return value to `[$amount * 1.1]` (an array), but doesn't mention updating the return type. A `?float` return type declaration will cause a TypeError when returning an array. The return type must change to `null|array` or `mixed`.

**File**: `docs/src/content/docs/packages/core.md` lines 50-57
**Fix**: Task 002 must explicitly require updating the return type alongside the return value.

### C2. Missing edge case test: empty array return (Task 001)

`is_array([])` returns `true`. If a before plugin returns an empty array `[]`, `$arguments` becomes `[]`, and then `$this->target->$method(...$arguments)` will throw a TypeError if the target method has required parameters. This is a real gotcha a developer will hit. Task 001 should include a test that documents this behavior (either as an expected error or as a guard).

**Fix**: Add a test requirement to Task 001 for the empty array case. At minimum, document that returning an empty array sets arguments to empty (which may cause errors if the target has required params). Consider whether `PluginProxy` should validate array length.

### C3. `concepts/plugins.md` before plugin return type is `null` (Task 002)

Line 44 shows `public function getPost(int $id): null` -- a return type of `null` (only allows returning null). With the new behavior, a before plugin that wants to modify args needs to return an array. The example return type is too restrictive. While this particular example is a pass-through (returns null), the surrounding docs describe argument modification as a capability, so at least one example needs a broader return type like `null|array` or `mixed`. Task 002 should update at least one before plugin example to show argument modification with the correct return type.

**Fix**: Task 002 should add an argument modification example to `concepts/plugins.md` with an appropriate return type, and note that pass-through-only plugins can keep `null` as return type.

## Important (Should fix before building)

### I1. After plugins receive modified arguments -- needs explicit documentation (Tasks 001, 002)

After the change, `$arguments` is reassigned when a before plugin returns an array. Line 55 of `PluginProxy.php` passes `...$arguments` to after plugins. This means after plugins receive the **modified** arguments, not the originals. The plan's architecture notes acknowledge this, and Task 001 includes a test for it. However, Task 002 does not mention documenting this behavior. The `concepts/plugins.md` docs say after plugins receive "the original arguments" (line 79). This needs to be updated to say "the arguments (possibly modified by before plugins)."

**Fix**: Add a requirement to Task 002 to update the after plugin documentation in `concepts/plugins.md` line 79 to reflect that arguments may have been modified by before plugins.

### I2. Argument count mismatch is unguarded (Task 001)

If a before plugin returns `['only-one-arg']` but the target method expects two parameters, PHP will throw a TypeError at `$this->target->$method(...$arguments)`. This is actually fine (loud errors, per project philosophy), but it should be documented in the tests as expected behavior. A test showing this throws would prevent future developers from adding unnecessary guard code.

**Fix**: Add a test to Task 001 that verifies a TypeError is thrown when the returned array has the wrong number of arguments. This documents the "loud errors" behavior.

### I3. Task 003 targets a file outside the repository (Task 003)

`~/Desktop/YOUTUBE.md` is outside the git repo. The worker needs to know this file won't be committed with the rest of the changes, and that lint/test commands won't apply to it. Additionally, the MarkoTalk file at `~/Sites/markotalk/app/message/src/Plugin/MarkdownPlugin.php` is in a separate project. Both files are outside the current working directory.

**Fix**: Add a note to Task 003 that these files are outside the repo and won't be covered by the project's test/lint pipeline.

### I4. Plan says "lines 38-46" but line numbers may shift (Task 001)

The plan and task reference specific line numbers in `PluginProxy.php`. The actual code block to modify is the `foreach` loop for before methods in the `__call()` method (lines 38-46 as of this review). This is fine for context but the worker should match on code structure, not line numbers. The task already includes the full code snippet, which is good.

**Fix**: Minor -- no change needed since the task includes the actual code snippet to match against.

## Minor (Nice to address)

### M1. The `blog.md` short-circuit example has a subtle type issue

In `docs/src/content/docs/packages/blog.md` line 155-165, `PostControllerPlugin::show()` returns `?string` and short-circuits with `'new-post'`. This means the *controller method* `show()` would receive `'new-post'` as its return value, skipping the actual controller logic. For a controller, this would presumably need to return a `Response`, not a string. This is a pre-existing documentation issue unrelated to this plan, but worth noting.

### M2. No test for before plugin returning an associative array

The proposed tests use sequential arrays for argument modification (e.g., `['modified-value', 42]`). Returning an associative array like `['name' => 'test']` and spreading it with `...` would pass values positionally in PHP 8.5 named argument unpacking. This is PHP behavior, not a Marko issue, but could surprise developers.

### M3. Task 003 MarkoTalk evaluation is low-value

Task 003 says to "evaluate" MarkoTalk's MarkdownPlugin and then immediately says "Decision: Leave as-is." The task is essentially pre-decided. The YouTube script update is the only real work. This task is small but not worth splitting further.

## Questions for the Team

### Q1. Should there be a way to short-circuit with an array value?

The plan explicitly puts this out of scope: "Edge case where target method returns an array and someone wants to short-circuit with an array value." This means it is **impossible** to short-circuit with an array return from a before plugin after this change. Is this acceptable long-term? Magento has the same limitation, which provides precedent, but it's a permanent API constraint.

### Q2. Should `PluginProxy` validate the returned array length?

When a before plugin returns an array for argument modification, should PluginProxy check that the array count matches the target method's parameter count? The current "loud errors" philosophy suggests letting PHP's TypeError speak, but a custom `PluginException` would be more helpful (e.g., "Before plugin X returned 1 argument for method Y which expects 2").

### Q3. Should the docs show the return type as `null|array` or `mixed`?

For before plugins that might modify arguments, the return type needs to accommodate `null` (pass-through), `array` (modify args), and potentially other types (short-circuit). `mixed` is the most flexible but least informative. `null|array|ReturnType` is explicit but verbose. Which convention should the docs recommend?
