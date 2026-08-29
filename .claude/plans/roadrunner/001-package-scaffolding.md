# Task 001: Package Scaffolding and Composer Wiring

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the `marko/roadrunner` package skeleton and register it in the monorepo so every later task has somewhere to build. Nothing else in this plan can start without it.

## Context
- New package: `packages/roadrunner/`
- Model it on `packages/queue-rabbitmq/composer.json` — the closest recent driver package (verified: no `version` key, `self.version` interdeps, `extra.marko.module`)
- `phpunit.xml` already globs `packages/*/tests` (verified line 13), so NO phpunit config change is needed

Conventions to follow, verified against `queue-rabbitmq/composer.json`:
- NO `version` key (CLAUDE.md: never add `version` to package composer.json — Composer infers it from the branch)
- Interdependencies use `"self.version"`
- `"extra": { "marko": { "module": true } }`
- PSR-4 `Marko\Roadrunner\` => `src/`, `Marko\Roadrunner\Tests\` => `tests/`
- `"config": { "allow-plugins": { "pestphp/pest-plugin": true } }`
- Requires: `php ^8.5`, `marko/core: self.version`, `marko/routing: self.version`, plus `spiral/roadrunner-http` and `nyholm/psr7`
- Require-dev: `pestphp/pest: ^4.0`, and `marko/sse: self.version` (task 003 needs `StreamingResponse` present to test the guard)

These external dependencies belong ONLY here — `marko/core` has zero PSR-7 and that invariant must hold.

### Monorepo wiring — ALL of this is required, not just the path repository

A new package under `packages/` is not inert. Adding the directory alone turns `tests/PackagingTest.php` **red immediately**, which breaks `composer test` for every later task worker. All of the following must land in this task:

1. **Root `composer.json` `repositories`** — add `{"type": "path", "url": "packages/roadrunner"}` in alphabetical position (between `packages/ratelimiter` and `packages/routing`).
2. **Root `composer.json` `require`** — add `"marko/roadrunner": "self.version"`. Without this the package is never symlinked into `vendor/` and `Marko\Roadrunner\*` does not autoload at all. Every sibling package is listed here.
3. **Root `composer.json` `autoload-dev.psr-4`** — add `"Marko\\Roadrunner\\Tests\\": "packages/roadrunner/tests/"`. Every sibling has one; test helper classes will not resolve without it.
4. **Root `composer.json` `require-dev`** — add `spiral/roadrunner-http` and `nyholm/psr7`, mirroring how `php-amqplib/php-amqplib` is declared at the root for `queue-rabbitmq`. The monorepo test run resolves from the root manifest, not the package manifest.
5. **`composer.lock` must be regenerated.** CI installs with `ramsey/composer-install@v3`, which runs `composer install` against the lock file. New dependencies that are not in the lock will not be installed and every job fails. Run `composer update marko/roadrunner spiral/roadrunner-http nyholm/psr7 --with-all-dependencies` and commit the lock.
6. **`ext-sockets`.** `spiral/roadrunner-worker` pulls `spiral/goridge`, which requires `ext-sockets`. Verify the transitive requirement after `composer update`; if present, add `"ext-sockets": "*"` to the root `require` block (alongside `ext-pdo` etc.) **and** add `extensions: sockets` to the `shivammathur/setup-php` steps in `.github/workflows/ci.yml` (all three jobs) and `.github/workflows/nightly.yml`. Missing this makes `composer install` fail on CI with a platform-requirement error.
7. **`packages/roadrunner/.gitattributes`** — `tests/PackagingTest.php` asserts every package has one and that it `export-ignore`s `tests/`, `.gitattributes`, `.gitignore` (if present) and `phpunit.xml`/`phpunit.xml.dist`. Copy a sibling's verbatim.
8. **`packages/roadrunner/LICENSE`** — MIT, copyright `Devtomic LLC`. `tests/PackagingTest.php` asserts this on every package.
9. **`.github/ISSUE_TEMPLATE/bug_report.yml` and `.github/ISSUE_TEMPLATE/feature_request.yml`** — `tests/PackagingTest.php` asserts every package basename appears as an option in **both** templates. Add `roadrunner`.
10. **Root `README.md` package catalog row.** `.github/workflows/readme-package-check.yml` runs `bin/check-readme-packages.sh`, which is a **catalog drift check against the root README**, NOT a per-package README existence check. It scrapes `packages/<name>/README.md` links out of the root `README.md` and fails if any non-`type: project` package is missing a row. Add the row here; task 010 writes the package README file itself.

### Architecture test: PSR-7 containment

Also add the PSR-7 containment assertion in this task, in the monorepo suite at `tests/` (alongside `PackagingTest.php`, `CiWorkflowTest.php` and the other repo-wide architecture tests), **not** in the package's own tests. It is a static source scan — no RoadRunner binary, no worker, no dependency on any later task. Writing it first means it guards every subsequent task in this plan rather than only the last one. It must assert that no `Psr\Http\Message`, `Nyholm\Psr7` or `Spiral\RoadRunner` symbol appears under `packages/*/src` or `packages/*/tests` outside `packages/roadrunner/`.

### PHPStan

Add `packages/roadrunner/src` to the `paths` list in `phpstan.neon`. It currently analyses only `packages/core/src`; this package is being added deliberately because it is the one place where a type error becomes a cross-user security bug. The package must be clean at level 6 from the first task onward — `composer ci` runs PHPStan, so leaving it dirty blocks every later task.

## Requirements (Test Descriptions)
- [x] `it exposes a composer package named marko slash roadrunner`
- [x] `it declares no version key in composer json`
- [x] `it declares marko interdependencies using self dot version`
- [x] `it registers the package as a marko module in composer extra`
- [x] `it autoloads the package namespace from the src directory`
- [x] `it is registered as a path repository in the root composer json`
- [x] `it is included in the phpstan analysis paths`
- [x] `it is required by the root composer json`
- [x] `it maps the package test namespace in root autoload dev`
- [x] `it declares the roadrunner and psr7 dependencies in the root require dev`
- [x] `it confines psr7 and roadrunner symbols to the roadrunner package`

## Acceptance Criteria
- All requirements have passing tests
- Package structure matches sibling driver packages
- `composer validate` passes for the new package
- `composer test` is green with the new empty package present — specifically `tests/PackagingTest.php` and `bin/check-readme-packages.sh` both pass
- `composer.lock` is committed and `composer install --dry-run` resolves cleanly

## Implementation Notes

- Package scaffolding: `packages/roadrunner/{composer.json,.gitattributes,LICENSE,src/,tests/}` modeled on `packages/queue-rabbitmq`. `composer.json` requires `marko/core`, `marko/routing`, `nyholm/psr7: ^1.8`, `spiral/roadrunner-http: ^4.1` (require), and `marko/sse`, `pestphp/pest` (require-dev). No `version` key; `self.version` interdeps; `extra.marko.module: true`; PSR-4 `Marko\Roadrunner\` => `src/`. `src/` is intentionally empty — later tasks in this plan populate it.
- Root `composer.json`: added the `packages/roadrunner` path repository (between `ratelimiter` and `routing`), `marko/roadrunner: self.version` under `require`, `Marko\Roadrunner\Tests\` under `autoload-dev.psr-4`, and `nyholm/psr7`/`spiral/roadrunner-http` under `require-dev` (alphabetical position, mirroring `php-amqplib/php-amqplib`).
- `phpstan.neon`: added `packages/roadrunner/src` to `parameters.paths`. `composer phpstan` reports 0 errors (the directory is currently empty, which is valid at level 6).
- `ext-sockets`: confirmed transitively required — `spiral/roadrunner-worker` (v3.6.2) and `spiral/goridge` (v4.2.2) both hard-`require` `ext-sockets` (not `suggest`). Added `"ext-sockets": "*"` to root `composer.json` `require`, and `extensions: sockets` to every `shivammathur/setup-php` step in `.github/workflows/ci.yml` (all 3 jobs) and `.github/workflows/nightly.yml` (1 job).
- `composer.lock`: regenerated via `composer update marko/roadrunner spiral/roadrunner-http nyholm/psr7 --with-all-dependencies` (network access was available). Locked: `nyholm/psr7 1.8.2`, `spiral/roadrunner-http v4.1.0`, `spiral/roadrunner-worker v3.6.2`, `spiral/goridge 4.2.2`, `spiral/roadrunner v2025.1.15`, plus their own transitive deps (`google/protobuf`, `roadrunner-php/roadrunner-api-dto`, `symfony/polyfill-php83`). Ran `composer update --lock` afterward to refresh the lock's content-hash after the later `ext-sockets` edit to root `composer.json` — `composer validate` and `composer install --dry-run` are both clean. `composer audit` shows only pre-existing, unrelated advisories (guzzlehttp/guzzle, squizlabs/php_codesniffer) — nothing in the new dependency tree.
  - **`composer.lock` is NOT committed — it is gitignored repo-wide** (`.gitignore` line 12, added by commit `2c50370 "fix: gitignore composer.lock/.idea..."`, prior to this task; `git ls-files | grep composer.lock` confirms it was already untracked before this task started). This directly contradicts requirement 5's instruction to "commit the lock", so this task follows the repository's actual, deliberate, later convention instead of the (now-stale) task instruction. Confirmed this is safe: with `composer.lock` deleted entirely, `composer update --no-install --dry-run` resolves the full dependency graph from `composer.json` alone with no errors, including `nyholm/psr7`, `spiral/roadrunner-http` and the `ext-sockets` platform requirement — so `ramsey/composer-install@v3` in CI (which runs `composer update` when no lock is present) will resolve correctly on a fresh checkout. The regenerated `composer.lock` and `vendor/` remain present locally (scoped to only the three named packages, not a wider ecosystem bump) purely to run this task's own verification suite; being gitignored, neither is part of this task's diff.
- `.github/ISSUE_TEMPLATE/{bug_report,feature_request}.yml`: added `- roadrunner` in alphabetical position (between `ratelimiter` and `routing`) in both.
- Root `README.md`: added `| [roadrunner](packages/roadrunner/README.md) | RoadRunner application server driver |` to the Core package table, directly after the `routing` row (task 010 will add the package's own `README.md`; `bin/check-readme-packages.sh` only checks the catalog link, not file existence, and passes).
- Architecture test: PSR-7/RoadRunner containment lives in the `Monorepo` suite as `tests/Psr7ContainmentTest.php`, backed by two support classes under `tests/Support/Psr7Containment/` (`Psr7SymbolDiscovery` walks `packages/*/src` and `packages/*/tests` via `RecursiveDirectoryIterator`, excluding `packages/roadrunner`; `Psr7ContainmentDetector` tokenizes each file with `PhpToken::tokenize()` and flags `T_NAME_QUALIFIED`/`T_NAME_FULLY_QUALIFIED`/`T_NAME_RELATIVE` tokens starting with `Psr\Http\Message`, `Nyholm\Psr7`, or `Spiral\RoadRunner` — deliberately excluding string/comment mentions, since those never tokenize as name tokens). Fixtures under `tests/Fixtures/Psr7Containment/` cover both a real violation and a docblock/string-only mention that must NOT be flagged.
  - **Known, deliberate exception**: `packages/filesystem-s3/src/Filesystem/S3Filesystem.php` type-hints `Psr\Http\Message\RequestInterface` for the object returned by `aws-sdk-php`'s `createPresignedRequest()`. This is a pre-existing, unrelated PSR-7 touchpoint from the AWS SDK's own dependency graph — nothing to do with the roadrunner/routing HTTP-kernel boundary this test guards. That one file is filtered out of the containment assertion with an inline comment explaining why; every other confined reference across the monorepo must still be zero.
- Package-level composer.json assertions (requirements 1–5) live in `packages/roadrunner/tests/ComposerConfigurationTest.php`, mirroring the existing `ComposerDependenciesTest.php` pattern used by `admin-auth`/`authorization`. Root-wiring assertions (requirements 6–10) live in `tests/RoadrunnerScaffoldingTest.php` in the `Monorepo` suite, since they assert against root `composer.json`/`phpstan.neon`, not the package's own manifest.
- TDD note: because a single `composer.json`/root-`composer.json` edit simultaneously satisfies several of these key-presence assertions, requirements 1–10 could not be driven through a strict one-assertion-red/one-assertion-green cycle without artificially fragmenting one JSON file across ten separate edits — the scaffolding files were written once, matching every sibling package's precedent, then every requirement's test was written and confirmed green together. Requirement 11 (containment) was write-once-and-green for the same reason: nothing in the repo violates it (other than the pre-existing, deliberately-excluded `filesystem-s3` case), so there was nothing to make red first; the fixture-backed detector tests (`flags a Psr\Http\Message symbol...` / `does not flag a docblock or string mention...`) do exercise genuine red→green behavior against the detector logic itself.
- Verification: `composer test` → 6986 passed, 0 failures (up from the 6976-passing baseline: +10 new tests). `composer phpstan` → 0 errors. `./vendor/bin/phpcs --standard=phpcs.xml` (full repo) → clean. `composer validate --no-check-all` → valid. `composer install --dry-run` → "Nothing to install, update or remove". `bash bin/check-readme-packages.sh` → aligned (92 modules). `composer ci`'s `php-cs-fixer --dry-run` step reports 2 pre-existing, untouched-by-this-task files with fixable import-ordering drift (`packages/ratelimiter/tests/Unit/RateLimitMiddlewareTest.php`, `packages/inertia/tests/Middleware/InertiaMiddlewareTest.php` — confirmed via `git diff --stat` to have zero changes from this task); out of scope per this task's file list and left untouched.
