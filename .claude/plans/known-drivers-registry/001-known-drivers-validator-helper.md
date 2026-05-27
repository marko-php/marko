# Task 001: Add KnownDriversValidator helper to marko/testing

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Create `KnownDriversValidator` in `marko/testing` providing shared assertion methods for the per-interface validation tests created in later tasks. Centralizes the comparison logic so each interface's test file is a thin wrapper, not a duplicated implementation.

## Context
- New file: `packages/testing/src/KnownDrivers/KnownDriversValidator.php`
- New test file: `packages/testing/tests/KnownDrivers/KnownDriversValidatorTest.php`
- Reference: existing helpers in `packages/testing/src/Fake/` for shape and namespace patterns

The class provides two static methods (each must skip-gracefully on missing files):

1. **`assertSkeletonSuggestContainsAll(string $knownDriversPath, string $skeletonComposerPath): void`** — reads known-drivers.php, locates skeleton's composer.json, asserts every entry from known-drivers.php is present in skeleton's `suggest` block (descriptions must match verbatim). Skeleton's suggest MAY contain additional entries (add-ons, etc.).
   **Skip behavior:** skip if (a) skeleton's composer.json is not on disk, OR (b) skeleton's composer.json exists but has no `suggest` key (still being built — task 024 populates it). Once skeleton has a `suggest` key, missing entries become hard failures. This three-state skip is REQUIRED so that per-interface validation tests in tasks 004, 007-023 can pass BEFORE task 024 runs. After task 024 lands, the skip falls through and real assertions fire.

2. **`assertDocsUrlsResolveToValidPattern(string $knownDriversPath): void`** — reads known-drivers.php and asserts every key matches the `marko/*` prefix pattern (URLs are derived from package names; entries that don't follow the pattern can't generate valid URLs).

**Note:** This class does NOT include `assertConflictBlocksMatch`. PR #92 settled the design question — Marko relies on DI-level `BindingConflictException` for double-binding detection, not Composer `conflict` declarations. So there are no conflict blocks to validate, only the skeleton-suggest parity.

## Requirements (Test Descriptions)
- [ ] `it reads driver list from known-drivers.php file`
- [ ] `it asserts skeleton suggest block contains all known drivers with matching descriptions`
- [ ] `it skips skeleton assertion gracefully when skeleton composer.json is not on disk`
- [ ] `it skips skeleton assertion when skeleton composer.json has no suggest key yet`
- [ ] `it fails skeleton assertion when skeleton has a suggest key but is missing a known driver entry`
- [ ] `it fails skeleton assertion when skeleton has a suggest entry but description does not match`
- [ ] `it allows skeleton suggest to contain entries beyond the known drivers list`
- [ ] `it asserts every known driver follows marko slash prefix pattern`
- [ ] `it fails loudly when the known-drivers.php file itself is missing`

## Acceptance Criteria
- `KnownDriversValidator` is a non-readonly class with two public static methods
- All file paths passed as parameters (no hardcoded paths inside the helper)
- **Skip mechanism:** since these are static methods (no bound `$this`), they cannot call `markTestSkipped()` directly. Throw `\PHPUnit\Framework\SkippedWithMessageException` (the same exception PHPUnit's `markTestSkipped()` throws under the hood). Pest treats this exception identically to `markTestSkipped()`. Alternative: throw a custom `KnownDriverAssertionSkipped` exception extending `\PHPUnit\Framework\SkippedTestError` for clearer semantics; either approach is acceptable.
- Comprehensive test coverage with fixture known-drivers.php files in `packages/testing/tests/KnownDrivers/fixtures/`
- **Performance:** validation methods read files synchronously from disk. For each assertion call, they parse one known-drivers.php file plus skeleton's composer.json. This is acceptable for CI. Do not memoize across calls — tests should be independent.
- Code follows code standards (strict_types, typed params/returns, `@throws` tags where applicable; `@throws \PHPUnit\Framework\SkippedWithMessageException` documented on each method)

## Implementation Notes
(Left blank — filled in by programmer during implementation)
