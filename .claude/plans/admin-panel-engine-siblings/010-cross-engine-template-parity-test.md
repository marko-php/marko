# Task 010: Add CrossEngineTemplateParityTest to marko/view

**Status**: pending
**Depends on**: 001, 006, 007
**Retry count**: 0

## Description
Create `packages/view/tests/Feature/CrossEngineTemplateParityTest.php` — the mechanical enforcement that adopting a new core view engine means shipping templates for every existing UI module. Scans all `marko/*` packages on disk, finds those declaring `extra.marko.templates_for`, and asserts that for every engine in `known-engines.php`, a corresponding template provider exists for every parent module.

## Context
- New file: `packages/view/tests/Feature/CrossEngineTemplateParityTest.php`
- Reads: `packages/view/known-engines.php` (from task 001)
- Scans: `packages/*/composer.json` files looking for `extra.marko.templates_for`

**Test logic shape:**

**Path-depth note:** From `packages/view/tests/Feature/CrossEngineTemplateParityTest.php`:
- `__DIR__` = `packages/view/tests/Feature`
- `dirname(__DIR__, 1)` = `packages/view/tests`
- `dirname(__DIR__, 2)` = `packages/view`        ← use for `known-engines.php`
- `dirname(__DIR__, 3)` = `packages`             ← use this directly to glob sibling packages

```php
test('every template provider has siblings for every registered core engine', function () {
    $packagesDir = dirname(__DIR__, 3); // resolves to the `packages/` directory in the monorepo
    $enginesPath = dirname(__DIR__, 2) . '/known-engines.php';

    if (!file_exists($enginesPath)) {
        $this->markTestSkipped('known-engines.php not found — marko/view not installed standalone?');
    }

    if (!is_dir($packagesDir) || basename($packagesDir) !== 'packages') {
        $this->markTestSkipped('Not running inside the monorepo packages directory — skipping cross-engine parity check.');
    }

    $engines = require $enginesPath;

    // Find all template provider packages (NOTE: $packagesDir IS the packages dir, no extra /packages segment)
    $providers = []; // shape: ['marko/admin-panel' => ['twig' => 'marko/admin-panel-twig', ...]]
    foreach (glob($packagesDir . '/*/composer.json') as $composerPath) {
        $composer = json_decode(file_get_contents($composerPath), associative: true);
        if (!is_array($composer)) continue;
        $templatesFor = $composer['extra']['marko']['templates_for'] ?? null;
        if (!is_string($templatesFor)) continue;

        $packageName = $composer['name'] ?? null;
        if (!is_string($packageName)) continue;

        // Derive engine suffix: marko/admin-panel-twig → 'twig'
        $engineSuffix = extractEngineSuffix($packageName, $templatesFor);
        if ($engineSuffix === null || !isset($engines[$engineSuffix])) continue;

        $providers[$templatesFor][$engineSuffix] = $packageName;
    }

    if ($providers === []) {
        $this->markTestSkipped('No template provider packages found — nothing to check.');
    }

    // Assert parity
    foreach ($providers as $parent => $foundEngines) {
        foreach ($engines as $engineName => $engineMeta) {
            expect($foundEngines)->toHaveKey(
                $engineName,
                "Parent module '$parent' has template providers for [" . implode(', ', array_keys($foundEngines))
                    . "] but is missing a provider for engine '$engineName'. "
                    . "Expected a package like 'marko/{basename}-{$engineName}' declaring "
                    . "extra.marko.templates_for: '$parent'."
            );
        }
    }
});
```

**Skip behaviors (zero-dependency principle):**
1. If `known-engines.php` doesn't exist → skip (marko/view not installed standalone)
2. If `dirname(__DIR__, 3)` is not a directory named `packages` (running outside monorepo) → skip
3. If no template provider packages found → skip (nothing to validate; vacuously true)

Note: This is a monorepo-only sanity check. When `marko/view` is installed in a downstream app under `vendor/`, the directory layout differs and the test will skip via the `basename === 'packages'` guard — by design.

**Engine suffix extraction:**
The test must derive which engine a provider package targets. Two approaches:
- (a) Suffix matching: `marko/admin-panel-twig` targets parent `marko/admin-panel`, suffix is `twig`
- (b) Read `extra.marko.engine` if present (would require a new metadata key — out of scope)

Use (a). Pest tests run at file scope (no class context) — `self::` is unavailable. Implement as a free function defined at the top of the test file:

```php
function extractEngineSuffix(string $packageName, string $parentName): ?string
{
    $packageBase = basename($packageName);      // 'admin-panel-twig'
    $parentBase = basename($parentName);        // 'admin-panel'
    $prefix = $parentBase . '-';
    if (!str_starts_with($packageBase, $prefix)) {
        return null; // doesn't follow convention; skip
    }
    return substr($packageBase, strlen($prefix)); // 'twig'
}
```

Use it as a plain function call: `extractEngineSuffix($packageName, $templatesFor)` (NOT `self::extractEngineSuffix(...)`).

**Negative test (drift detection):**
Add a second test that simulates a missing engine: create a temporary fixture directory where `marko/admin-panel-latte` exists but no Twig provider; assert the parity check fails with a clear error message. Easiest implementation: extract the parity logic into a static method, call it with mocked input, assert the exception message.

## Requirements (Test Descriptions)
- [ ] `it asserts every template provider has a sibling for every registered engine (passing case)`
- [ ] `it fails with a clear error message when an engine is missing a provider for some parent`
- [ ] `it skips gracefully when known-engines.php is not present`
- [ ] `it skips gracefully when the resolved packages directory is not the monorepo packages/ dir`
- [ ] `it skips gracefully when no template provider packages are found`
- [ ] `it correctly extracts engine suffix from package name (admin-panel-twig → twig)`
- [ ] `it ignores packages whose names do not follow the marko/{parent}-{engine} convention`
- [ ] `it ignores packages whose extracted suffix is not in known-engines (e.g., admin-panel-twig-extra produces suffix twig-extra and is skipped if not registered)`

## Acceptance Criteria
- `CrossEngineTemplateParityTest.php` exists in `packages/view/tests/Feature/`
- Test passes against the current monorepo state (after tasks 006 and 007 ship both Latte and Twig admin-panel templates)
- Skip behaviors work correctly — verify by mentally walking through the skip logic (or by temporarily renaming known-engines.php and confirming the test skips)
- Error message names the missing engine, the parent module, and suggests the expected package name
- Code follows code standards (strict_types, expectation chaining)
