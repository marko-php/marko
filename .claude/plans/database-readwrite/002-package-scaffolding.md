# Task 002: Package Scaffolding

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Scaffold the `packages/database-readwrite/` directory with the standard Marko module layout: `composer.json`, `module.php` (placeholder — boot wiring comes in task 011), `LICENSE`, `.gitattributes`, `README.md` (placeholder — final README in task 015), empty `src/` and `tests/` directories with the namespace ready, plus all root-level metadata updates so the package is discovered, validated, and packaged.

## Context
- **Reference packages to mirror exactly:** Read `packages/queue-database/` AND `packages/session-database/` for layout, composer.json shape, module.php conventions, .gitattributes, LICENSE. Follow `.claude/sibling-modules.md` and `.claude/module-development.md` rigorously — naming, structure, namespace conventions.
- **Package name:** `marko/database-readwrite`
- **Namespace:** `Marko\Database\ReadWrite\` (PSR-4 root = `packages/database-readwrite/src/`)
- **Test namespace:** `Marko\Database\ReadWrite\Tests\` (root = `packages/database-readwrite/tests/`)
- **composer.json requirements:**
  - `name`, `description`, `license: MIT`, `type: library`
  - `require`: `php: ^8.5`, `marko/core: self.version`, `marko/database: self.version`, `ext-pdo: *`
  - Do NOT require `marko/database-pgsql` or `marko/database-mysql` — driver-agnostic by design; user installs whichever driver they want alongside.
  - `require-dev`: `pestphp/pest: ^4.0`
  - `autoload` / `autoload-dev` PSR-4 mappings
  - `extra.marko.module: true`
  - `config.allow-plugins.pestphp/pest-plugin: true`
  - **No `version` key** (per CLAUDE.md: never hardcode versions in package composer.json).
- **module.php (placeholder for this task):** Must be a valid PHP module manifest returning an array. Bindings can be empty or omitted in this task; boot callback comes in task 011. Just enough so module discovery doesn't fail.
- **README placeholder:** A 1-line stub like "See docs at https://marko.build/docs/packages/database-readwrite/". Final README in task 015.
- **Root-level files to update (verified by grep — these are the actual hard-coded references):**
  - Root `composer.json` — add path repository entry under `repositories`, add to `require` with `self.version`, add tests namespace autoload entry under `autoload-dev.psr-4` (verify by searching for `Marko\\Database\\PgSql\\Tests\\` in root composer.json)
  - Root `README.md` — package list (grep `database-pgsql` in `README.md` to find the table row)
  - `.github/ISSUE_TEMPLATE/bug_report.yml` AND `.github/ISSUE_TEMPLATE/feature_request.yml` — add `database-readwrite` to the package dropdown (both files contain `- database-pgsql` entries to insert near)
  - `packages/framework/tests/RootComposerJsonTest.php` — line 28 has a hard-coded expected-package list; add `'marko/database-readwrite'` in alphabetical order
- **Files that DO NOT need updates (verified — they auto-discover packages by `scandir('packages')`):**
  - `tests/PackagingTest.php` — uses dynamic scandir at line 7-10
  - `tests/IntegrationVerificationTest.php` — uses dynamic scandir at line 8-11
  - `docs/astro.config.mjs` — uses `autogenerate: { directory: 'packages' }` for the Packages sidebar
  - `bin/release.sh` — auto-discovers split repos from `packages/` directory
- **Find any newly added references with:** `grep -rln 'marko/database-pgsql' --include='*.json' --include='*.yml' --include='*.php' --include='*.md' . | grep -v vendor | grep -v node_modules | grep -v docs/dist | grep -v .claude/plans` — use this as a sanity check after edits to confirm nothing else hardcodes the package name list.

## Requirements (Test Descriptions)
- [x] `it has a valid composer.json with correct name and namespace`
- [x] `it declares marko/core and marko/database as required dependencies`
- [x] `it does not hardcode a version key in composer.json`
- [x] `it does not require any specific database driver package`
- [x] `it has a module.php that loads without error`
- [x] `it appears in the root composer.json path repositories list`
- [x] `it appears in the root composer.json require section with self.version`
- [x] `it appears in RootComposerJsonTest expected-package list`
- [x] `it is auto-discovered by PackagingTest via scandir (no list update needed — just a smoke check that the new directory satisfies the .gitattributes assertion)`
- [x] `it appears in both .github/ISSUE_TEMPLATE/bug_report.yml and feature_request.yml package dropdowns`

## Acceptance Criteria
- `packages/database-readwrite/composer.json` validates: `composer validate packages/database-readwrite/composer.json --strict --no-check-publish --no-check-lock`
- Root `composer.json` still validates: `composer validate --strict --no-check-publish --no-check-lock`
- `composer test` passes (the new package's `tests/` may be empty or have just packaging-level assertions at this point).
- Module is discovered (running `marko module:list` would show it, but this is not required to test — module manifest just needs to be valid).
- `./vendor/bin/phpcs packages/database-readwrite/` clean.
- `./vendor/bin/php-cs-fixer fix packages/database-readwrite/ --dry-run --diff` clean.
- No existing test in any other package breaks.

## Implementation Notes
- Created `packages/database-readwrite/` with: `composer.json`, `module.php` (empty bindings placeholder), `LICENSE`, `.gitattributes`, `README.md` (stub), `src/` and `tests/` directories
- Root `composer.json` updated: path repository entry after `database-pgsql`, require entry `marko/database-readwrite: self.version`, autoload-dev PSR-4 entry `Marko\\Database\\ReadWrite\\Tests\\`
- `packages/framework/tests/RootComposerJsonTest.php` updated with `marko/database-readwrite` in alphabetical order
- Both `.github/ISSUE_TEMPLATE/bug_report.yml` and `feature_request.yml` updated with `- database-readwrite` dropdown option
- Root `README.md` updated with table row in Database section
- Tests use `dirname(__DIR__, 3)` to reach project root (tests are 3 levels deep: `packages/database-readwrite/tests/`)
- `composer test` goes from 13 failures to 1 pre-existing failure (SplitWorkflowTest/SPLIT_TOKEN unrelated to this task)
