# Task 009: Core manifest `php*`-vendor dependency filter fix

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
`ManifestParser::extractMarkoRequirements()` filters out any composer requirement whose key starts with `php` (via `!str_starts_with($package, 'php')`). This wrongly drops legitimate vendor packages such as `phpunit/phpunit`, `phpstan/phpstan`, etc. Restrict the platform filter to true platform packages only: exact `php`, `php-64bit`, and prefixes `ext-` / `lib-`.

## Description-note
The current filter intent is to drop non-module platform deps before extracting Marko module requirements. The fix narrows the `php` check from a prefix match to an exact-match (plus `php-64bit`) so vendor packages named `php...` are preserved.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/core/src/Module/ManifestParser.php` (`extractMarkoRequirements()` ~129-149; the `!str_starts_with($package, 'php')` clause)
  - Tests: `/Users/markshust/Sites/marko/packages/core/tests/Unit/Module/ManifestParserTest.php`
- Patterns to follow:
  - Replace `!str_starts_with($package, 'php')` with an exact platform check: drop when `$package === 'php' || $package === 'php-64bit'`, and keep the existing `ext-`/`lib-` prefix drops. Case: platform package names are lowercase by Composer convention; an exact `=== 'php'` match is correct (do not lowercase-normalize — composer keys for `php` are always lowercase).
  - CORRECTION to a prior assumption: `extractMarkoRequirements()` does NOT filter down to only `marko/*` anywhere — it returns EVERY surviving key (e.g. `psr/log`, `phpunit/phpunit`). The bug is real and not masked by any downstream step: today `phpunit/phpunit` is dropped solely by the `str_starts_with($package, 'php')` prefix. So the `it keeps marko/* module requirements` test should also confirm a non-marko vendor package like `psr/log` survives, proving the filter only removes true platform deps.
  - Keep `ARRAY_FILTER_USE_KEY` and the closure shape.

## Requirements (Test Descriptions)
- [ ] `it does not drop a phpunit/phpunit requirement`
- [ ] `it does not drop a phpstan/phpstan requirement`
- [ ] `it drops the exact php platform requirement`
- [ ] `it drops the php-64bit platform requirement`
- [ ] `it drops ext-* requirements`
- [ ] `it drops lib-* requirements`
- [ ] `it keeps marko/* module requirements`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
