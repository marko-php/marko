# Task 010: Integration Verification

**Status**: complete
**Depends on**: 001, 002, 003, 007
**Retry count**: 0

## Description
Verify that all changes work together: local development is not broken, all composer.json files validate, and the existing test suite passes. This is the final sanity check before the publishing infrastructure is considered complete.

## Context
- After Tasks 001, 002, 003, and 007, composer.json files and project structure have been modified
- Root composer.json now uses Symfony pattern (require + replace + path repos, no manual PSR-4)
- All package composer.json files have self.version constraints and no path repos
- demo/ directory has been removed
- .gitattributes added to all packages
- Need to ensure `composer install` / `composer update` works from root
- Need to ensure the full test suite still passes
- PHP binary: `/opt/homebrew/Cellar/php/8.5.1_2/bin/php`

## Requirements (Test Descriptions)
- [x] `it validates all 70 package composer.json files are valid JSON with required keys (name, require) via structural check -- NOTE: composer validate cannot be run on individual sub-packages because self.version is not a valid constraint outside the monorepo root context`
- [x] `it validates the root composer.json passes composer validate`
- [x] `it removes composer.lock and vendor/ before running composer update to ensure clean resolution`
- [x] `it runs composer update from root successfully`
- [x] `it runs the full test suite and all tests pass`
- [x] `it verifies no package composer.json contains a repositories key`
- [x] `it verifies no package composer.json contains a version key`
- [x] `it verifies all marko/* dependencies use self.version constraint`
- [x] `it verifies every package directory has a .gitattributes file`
- [x] `it verifies every package directory has a LICENSE file`
- [x] `it verifies demo/ directory no longer exists`

## Acceptance Criteria
- Root composer.json passes `composer validate`
- Sub-package composer.json files pass structural validation (valid JSON, required keys present, no `repositories`, no `@dev`, no `version` field)
- Note: `composer validate` on individual sub-packages will fail because `self.version` is not a standard constraint -- this is expected and correct; it only resolves in the context of a git tag or root `replace`
- `composer.lock` and `vendor/` are removed before running `composer update` for clean verification
- `composer update` completes without errors from monorepo root (this proves the Symfony-pattern autoloading works)
- Full test suite passes (`./vendor/bin/pest --parallel`)
- Grep confirms zero occurrences of `"repositories"` in package composer.json files
- Grep confirms zero occurrences of `"@dev"` in package composer.json files
- Grep confirms zero occurrences of wildcard `"*"` constraints for marko packages
- Every `packages/*/` has a `.gitattributes`
- Every `packages/*/` has a `LICENSE` (MIT, Copyright Devtomic LLC)
- `demo/` does not exist

## Implementation Notes
Run verification script:
```bash
# Structural validation of all package composer.json files
for f in packages/*/composer.json; do
  /opt/homebrew/Cellar/php/8.5.1_2/bin/php -r "json_decode(file_get_contents('$f'), true, 512, JSON_THROW_ON_ERROR);" || echo "INVALID JSON: $f"
done

# Validate root (self.version in replace section is valid here)
composer validate

# Verify demo/ is gone
[[ ! -d demo ]] || echo "FAIL: demo/ still exists"

# Verify .gitattributes and LICENSE exist
for dir in packages/*/; do
  [[ -f "$dir/.gitattributes" ]] || echo "MISSING .gitattributes: $dir"
  [[ -f "$dir/LICENSE" ]] || echo "MISSING LICENSE: $dir"
done

# Clean install to verify replace + path repos + package-level autoload work together
rm -rf composer.lock vendor/
composer update

# Run tests
/opt/homebrew/Cellar/php/8.5.1_2/bin/php vendor/bin/pest --parallel

# Verify no path repos in packages
grep -r '"repositories"' packages/*/composer.json  # should return nothing

# Verify no @dev constraints for marko packages
grep -r '"@dev"' packages/*/composer.json  # should return nothing

# Verify no wildcard constraints for marko packages
grep -rP '"marko/[^"]+": "\*"' packages/*/composer.json  # should return nothing
```
