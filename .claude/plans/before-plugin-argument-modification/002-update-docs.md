# Task 002: Update Documentation and READMEs

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Update all documentation that references before plugin behavior to accurately describe the three return value behaviors: null (pass through), array (modify arguments), non-null non-array (short-circuit). Several docs already partially describe argument modification but inconsistently. Also document after plugin result chaining — that each after plugin's return value becomes the next after plugin's input, not just a one-shot modification.

## Context
Files to update:

1. **`docs/src/content/docs/concepts/plugins.md`** — Primary plugin documentation
   - Line 14: Table already says "Modify input arguments" but doesn't explain how
   - Line 41: Comment says "return null to continue, or non-null to short-circuit" — needs third option
   - Line 44: The before plugin example has return type `null` — this only allows pass-through. Add a separate example (or update this one) showing argument modification with a return type that allows arrays (e.g., `null|array` or `mixed`)
   - Line 77: "Return `null` to continue to the original method, or return a non-null value to short-circuit" — needs array case added
   - Line 79: "followed by the original arguments" — update to "followed by the arguments (possibly modified by before plugins)" since after plugins receive `...$arguments` which may have been reassigned by a before plugin
   - Add an example showing argument modification with correct return type

2. **`docs/src/content/docs/packages/core.md`** — Core package API reference
   - Lines 50-57: The `PaymentValidationPlugin` example returns `$amount * 1.1` from a before plugin. This currently short-circuits (bug in docs). It should be updated to return an array `[$amount * 1.1]` to actually modify the argument.
   - **IMPORTANT**: The return type `?float` must also change to `null|array` (or `mixed`) — returning an array from a method typed `?float` will cause a TypeError.

3. **`docs/src/content/docs/packages/blog.md`** — Blog package docs
   - Lines 155-165: Before plugin example returns `'new-post'` string for short-circuit — this is fine as-is (short-circuit is the intent)

4. **`packages/blog/README.md`** — Blog package README
   - Lines 83-88: Uses outdated `beforeCreatePost` naming convention. Update to current method naming pattern. Also add note about argument modification.

5. **`.claude/architecture.md`** — Architecture reference
   - Line 654: "Before: Runs before the original method. Can short-circuit by returning early." — add argument modification
   - Line 671: Plugin discovery description — ensure it mentions the three return behaviors

6. **`README.md`** (project root)
   - Line 35: "Modify inputs, transform outputs" — already implies this, just verify it's accurate

7. **After plugin chaining** (across docs)
   - `concepts/plugins.md` line 79 says "Return the (possibly modified) result" but doesn't explain that when multiple after plugins target the same method, each one's return value becomes the next one's `$result` parameter. Add a brief note or example showing the chain: target returns 10 → plugin A doubles to 20 → plugin B adds 5 → final result 25.
   - `architecture.md` line 655 says "Can modify the return value" — add "Each after plugin's return value is passed to the next after plugin in sort order."

## Requirements (Test Descriptions)
- [ ] `it updates concepts/plugins.md with three return value behaviors and an argument modification example`
- [ ] `it adds an argument modification example to concepts/plugins.md with a return type that allows arrays`
- [ ] `it updates concepts/plugins.md after plugin description to note arguments may have been modified by before plugins`
- [ ] `it fixes core.md PaymentValidationPlugin to return array for argument modification AND updates the return type from ?float to null|array (or mixed)`
- [ ] `it updates blog README to use current method naming and document argument modification`
- [ ] `it updates architecture.md before plugin description to include argument modification`
- [ ] `it keeps blog.md short-circuit example unchanged since it correctly demonstrates short-circuit`
- [ ] `it documents after plugin result chaining in concepts/plugins.md — each after plugin's return becomes the next one's input`
- [ ] `it adds after plugin chaining note to architecture.md`

## Acceptance Criteria
- All before plugin documentation consistently describes three behaviors: null, array, non-null non-array
- No docs claim behavior that doesn't exist in the code
- Code examples in docs are correct and would actually work
- Blog README uses current method naming convention (not beforeCreatePost)

## Implementation Notes
(Left blank - filled in by programmer during implementation)
