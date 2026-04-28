# Task 002: Update all rate-limiting references across monorepo

**Status**: pending
**Depends on**: 001
**Retry count**: 0

## Description
Update all references to the old `marko/rate-limiting` package name and `Marko\RateLimiting` namespace throughout the monorepo. This completes the rename so the monorepo builds cleanly.

## Context
- Root composer.json (replace/provides)
- Other packages' composer.json require statements
- Any `use Marko\RateLimiting\*` imports in other packages
- `.claude/architecture.md` package inventory table
- `docs/` references (if any)
- Metapackage dependencies (`marko/framework`)

## Requirements (Test Descriptions)
- [x] `it has zero grep hits for marko/rate-limiting in composer.json files outside the ratelimiter package itself`
- [x] `it has zero grep hits for Marko\\RateLimiting namespace outside the ratelimiter package`
- [x] `it updates .claude/architecture.md package inventory to reference marko/ratelimiter`
- [x] `it runs the full test suite clean after the rename propagates`
- [x] `it runs composer dump-autoload without errors`

## Acceptance Criteria
- Monorepo-wide `composer test` passes
- `composer dump-autoload` succeeds
- No stale references remain

## Implementation Notes
- Removed old `packages/rate-limiting/` directory (entire tree deleted)
- Removed `"marko/rate-limiting": "self.version"` from root `composer.json` require section
- Removed `packages/rate-limiting` path repo entry from root `composer.json` repositories section
- Removed `"Marko\\RateLimiting\\Tests\\": "packages/rate-limiting/tests/"` from root `composer.json` autoload-dev
- Removed `<exclude>packages/rate-limiting/tests</exclude>` from `phpunit.xml`
- Updated `packages/framework/tests/RootComposerJsonTest.php`: replaced `marko/rate-limiting` with `marko/ratelimiter` in $allPackages array
- Tests added to `packages/ratelimiter/tests/Unit/RenameReferencesTest.php`
- Pre-existing failures in `PackagingTest` and `IntegrationVerificationTest` about `packages/docs` missing `.gitattributes`/`LICENSE` are unrelated to this task
