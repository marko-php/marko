# Task 002: Fix Latte Engine Factory (Test + Source)

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Two issues: (1) The factory calls `$engine->setStrictTypes()` which is deprecated — migrate to `$engine->setFeature(Feature::StrictTypes, ...)`. (2) The test uses reflection to access a non-existent `$strictTypes` property — fix to use `$engine->hasFeature(\Latte\Feature::StrictTypes)`.

## Context
- Related files: `packages/view-latte/tests/LatteEngineFactoryTest.php`, `packages/view-latte/src/LatteEngineFactory.php`
- The factory calls `$engine->setStrictTypes($this->viewConfig->strictTypes())` — this uses the deprecated API
- The modern Latte API is `$engine->setFeature(\Latte\Feature::StrictTypes, $value)` and `$engine->hasFeature(\Latte\Feature::StrictTypes)`
- The test at line ~100 does `$reflection->getProperty('strictTypes')` which throws `ReflectionException`
- Latte Engine defaults `Feature::StrictTypes` to `true`, so the test must verify that setting it to `false` actually changes the value

## Requirements (Test Descriptions)
- [ ] `it configures strict types` — in the factory source, replace `$engine->setStrictTypes(...)` with `$engine->setFeature(\Latte\Feature::StrictTypes, ...)`. In the test, replace the reflection-based approach with `$engine->hasFeature(\Latte\Feature::StrictTypes)`. Remove all `ReflectionClass` usage. Keep the two sub-tests: one with `strictTypes` true, one with false.
- [ ] All other Latte engine factory tests continue to pass unchanged (do NOT modify any other test in the file)

## Acceptance Criteria
- All requirements have passing tests
- Factory no longer calls deprecated `setStrictTypes()` — uses `setFeature()` instead
- Test no longer uses reflection — uses `hasFeature()` instead
- Test still meaningfully verifies that strict types configuration is applied
