# Task 005: discovery:clear command

**Status**: pending
**Depends on**: [002]
**Retry count**: 0

> PREREQUISITE: This plan must land AFTER `tier2-high-correctness/003-classfileparser-tokenizer` is merged. See `_plan.md` Prerequisite note.

## Description
Add the `discovery:clear` CLI command. It removes the compiled discovery cache file via `DiscoveryCache::clear()`, reporting success. Clearing is idempotent: clearing when no cache exists is reported as success, not an error.

**Recovery-path limitation (must be documented in the command and the corrupt-cache exception, Task 002).** `discovery:clear` runs AFTER `Application::initialize()` (see `CliKernel::doRun()`), and Task 006 makes a CORRUPT cache throw during `initialize()`. So `discovery:clear` CANNOT recover a corrupt cache — boot throws before the command dispatches. It only works when boot succeeds (cache valid or absent). The recovery instruction for a corrupt cache is to delete the file directly; the `DiscoveryCacheException` suggestion (Task 002) names that path first. `discovery:clear` is the convenience path for a valid cache you simply want to remove.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/core/src/Commands/DiscoveryClearCommand.php` (NEW)
  - `/Users/markshust/Sites/marko/packages/core/tests/Unit/Commands/DiscoveryClearCommandTest.php` (NEW)
  - `/Users/markshust/Sites/marko/packages/core/src/Discovery/DiscoveryCache.php` (Task 002)
- Patterns to follow:
  - `packages/page-cache/src/Command/ClearCommand.php` — closest analog: `#[Command(name: 'page-cache:clear', ...)]`, `readonly`, injects the cache component, `execute()` calls `clear()` and writes a result line. Model `discovery:clear` on it directly (name `discovery:clear`, description `Remove the discovery cache`).
  - `Output` exposes only `write()` / `writeLine()`.
  - `ClearCommand` lives in `marko/page-cache` and injects a `PageCacheInterface`; `DiscoveryClearCommand` lives in `marko/core` and injects `DiscoveryCache` directly (a concrete autowirable class). Do NOT mirror page-cache's `marko/config` dependency.
- Standards: strict types, constructor promotion, no final, no magic methods, full type declarations. MUST NOT reference `Marko\Config`.

## Requirements (Test Descriptions)
- [ ] `it removes an existing cache file and returns exit code 0`
- [ ] `it reports the cache as cleared via writeLine`
- [ ] `it returns exit code 0 and reports success when no cache file exists`
- [ ] `it leaves DiscoveryCache exists() false after running`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
