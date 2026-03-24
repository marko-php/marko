# Devil's Advocate Review: fix-test-failures

## Critical (Must fix before building)

### C1. Task 001 test description does not match actual test assertions (task 001)
The task says to update the test to check for `git checkout main` and `git merge develop`. But the actual test at line 54-59 of `tests/ReleaseScriptTest.php` asserts:
```php
expect($contents)->toContain('main')
    ->and($contents)->toContain('git branch --show-current')
    ->and($contents)->toContain("Must be on 'main' branch");
```
The script has `git checkout main` and `git merge develop` (lines 24-26) which already contains `'main'`. The worker needs to know the EXACT current assertions to replace them. The fix must replace `git branch --show-current` with `git checkout main` and `Must be on 'main' branch` with `git merge develop`.

**Fix:** Add exact current test assertions and exact new assertions to task 001.

### C2. Task 002 approach is too vague -- worker needs the actual Latte API (task 002)
The task says "verify strict types configuration without using reflection" but gives no concrete approach. The Latte Engine has a public `hasFeature(Feature::StrictTypes)` method that returns a bool -- this is the correct way to verify. Also, `setStrictTypes()` is deprecated in favor of `setFeature(Feature::StrictTypes, ...)`, though the factory still uses it (which works fine since it delegates internally).

**Fix:** Specify using `$engine->hasFeature(\Latte\Feature::StrictTypes)` as the verification approach.

### C3. Task 003 requirement count is wrong -- lists 10 requirements but claims 9 in acceptance criteria (task 003)
The Requirements section lists 10 bullet points but Acceptance Criteria says "All 9 Pest expectation tests pass". The actual failing tests are: 6 in ExpectationsTest + 4 in FakeGuardTest = 10 total.

**Fix:** Update acceptance criteria to say 10.

### C4. Plan objective miscounts Pest failures as 7 instead of 10 (_plan.md)
The plan says "7 Pest expectations tests" in the objective, yielding the 41 total (1+1+7+32). The actual count is 10 (6 in ExpectationsTest + 4 in FakeGuardTest), making the real total 44 (1+1+10+32).

**Fix:** Correct the objective count to 10 and total to 44.

### C5. Task 005 says test accepts `## Configuration` or `## Config` but test only accepts `## Configuration` (task 005)
The actual test at `packages/authentication/tests/ReadmeTest.php` line 23 checks exactly `->toContain('## Configuration')`. There is no `## Config` alternative. If a worker writes `## Config` instead, the test will fail.

**Fix:** Remove the `or ## Config` alternative from task 005.

### C6. Task 007 requirement names do not match actual test names (task 007)
The task lists 10 requirements with made-up names. The actual blog ReadmeTest has 12 `it()` blocks with specific names. A worker running specific tests won't find them by the names in the task. More importantly, the task merges several tests into combined requirements (e.g., "documents installation" merges 2 tests, "documents view requirements and template overriding" merges 3 tests), which could cause a worker to miss assertions.

**Fix:** List actual test names from `packages/blog/tests/Documentation/ReadmeTest.php`.

### C7. Task 007 title mismatch: current README has `# marko/blog` but test expects `# Marko Blog` (task 007)
The current blog README line 1 is `# marko/blog`. The test checks `toContain('# Marko Blog')`. The task mentions this in its requirements but the worker needs to know they must change the title format, breaking from the slim README convention used elsewhere (which uses `# marko/{name}`).

**Fix:** Explicitly note in task 007 that the title must be `# Marko Blog` (not `# marko/blog`).

### C8. Task 008 database README case-sensitivity issue (task 008)
The current database README has `Entity-driven schema` (lowercase 'd', lowercase 's') but the test checks `toContain('Entity-Driven Schema')` (capital 'D', capital 'S'). This is a case-sensitive match. The task must specify the exact casing required.

**Fix:** Add exact casing requirements to task 008.

## Important (Should fix before building)

### I1. Task 006 dependency on 003 may be unnecessary for the README itself (task 006)
Task 006 depends on task 003 with the rationale "tests for Pest expectations must work first." But the README tests in `packages/testing/tests/ReadmeTest.php` only check that strings like `toHaveAttempted` and `toBeAuthenticated` appear in the README text -- they don't call any Pest expectations. The dependency is only needed if the test file itself uses custom expectations, which it does not. However, if running `pest --parallel` for the entire package, the ExpectationsTest would still fail without fix 003. This dependency is correct for integration validation but could slow parallel execution.

### I2. Task 002 should note that setStrictTypes() is deprecated (task 002)
The factory at `packages/view-latte/src/LatteEngineFactory.php` line 21 calls `$engine->setStrictTypes()` which is deprecated. While fixing the test is the immediate goal, the worker should be aware this exists but should NOT fix it (out of scope). The task should explicitly say not to change the factory code.

**Fix:** Add note to task 002 clarifying scope is test-only, do not update factory code.

### I3. Task 008 database README already has partial content that must be preserved/extended (task 008)
The current `packages/database/README.md` already has a Post entity example with `primaryKey`, `autoIncrement`, `unique` in the Quick Example section. The test also checks for `nullable` and `default` as strings. The current example uses PHP nullable syntax (`?string`) and PHP default values (`= null`) but does NOT contain the literal strings `nullable` and `default` as Column attribute parameters. The worker needs to add `nullable: true` and `default: ...` to Column attributes in the example.

**Fix:** Note in task 008 that existing Quick Example must be extended with `nullable` and `default` Column attribute parameters.

### I4. Blog test has a docs-link test that likely already passes (task 007)
The blog ReadmeTest doesn't explicitly have a `toContain('Documentation')` test, but the current README has a Documentation section with a link. The task should clarify which tests already pass so the worker doesn't break them.

### I5. Task 007 is very large -- 12 test expectations across many sections (task 007)
The blog README task requires writing the most content-heavy README with very specific test expectations (exact strings, regex patterns, 10 events, 8 routes, 5 config keys, 3 extensibility mechanisms). This is significantly larger than other README tasks. A worker might time out or produce inconsistent content.

## Minor (Nice to address)

### M1. Latte deprecation
`LatteEngineFactory::create()` calls deprecated `setStrictTypes()`. Should be migrated to `setFeature(Feature::StrictTypes, ...)` in a future task.

### M2. Default strict types in Latte
The Latte Engine defaults `Feature::StrictTypes` to `true` (line 57 of Engine.php). This means even if `setStrictTypes(false)` is called, the test must verify it was changed FROM the default. The `hasFeature` approach handles this correctly.

### M3. README style inconsistency
Blog test expects `# Marko Blog` (title case) while all other packages use `# marko/{name}` (lowercase). This is an inconsistency but driven by the test itself.

## Questions for the Team

1. **Blog README title convention:** The blog test expects `# Marko Blog` while all other packages use `# marko/{name}`. Is this intentional for the blog module (as a "product" vs a "package"), or should the test be updated?

2. **Total failure count:** The plan claims 41 failures but the actual count appears to be 44 (10 Pest expectation failures, not 7). Has someone verified the exact count by running `pest --parallel`?

3. **Latte deprecation:** Should a follow-up task be created to migrate `setStrictTypes()` to `setFeature(Feature::StrictTypes, ...)`?
