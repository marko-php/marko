# Task 004: discovery:cache command

**Status**: pending
**Depends on**: [002, 003]
**Retry count**: 0

> PREREQUISITE: This plan must land AFTER `tier2-high-correctness/003-classfileparser-tokenizer` is merged. See `_plan.md` Prerequisite note.

## Description
Add the `discovery:cache` CLI command. It uses `DiscoveryCompiler` to build the payload from the loaded modules and `DiscoveryCache` to write it to disk, then reports the counts and the file path. It exits non-zero with a helpful message when the cache cannot be written.

The command writes to the SAME cache file that boot (Task 006) reads. Both obtain
`DiscoveryCache` from the container (constructed with the same `ProjectPaths` +
`DiscoveryEnvironment`), so the path is guaranteed consistent. Inject `DiscoveryCache`
and `DiscoveryCompiler` (autowirable concrete classes) — do not reconstruct them with
ad-hoc paths.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/core/src/Commands/DiscoveryCacheCommand.php` (NEW)
  - `/Users/markshust/Sites/marko/packages/core/tests/Unit/Commands/DiscoveryCacheCommandTest.php` (NEW)
  - `/Users/markshust/Sites/marko/packages/core/src/Discovery/DiscoveryCompiler.php` (Task 003)
  - `/Users/markshust/Sites/marko/packages/core/src/Discovery/DiscoveryCache.php` (Task 002)
- Patterns to follow:
  - `packages/page-cache/src/Command/ClearCommand.php` and `packages/core/src/Commands/ListCommand.php` — `readonly` class, `#[Command(name: 'discovery:cache', description: 'Compile the discovery cache')]`, `implements CommandInterface`, constructor-promoted dependencies, `execute(Input $input, Output $output): int`, `/** @noinspection PhpUnused */` docblock above the class.
  - `Output` exposes only `write()` / `writeLine()` — use `writeLine` for all messages; return `0` on success, non-zero on failure.
  - How the command obtains the resolved module list to compile: inject `ModuleRepositoryInterface` (bound in the container at boot, line ~171 of `Application`) and pass `->all()` to `DiscoveryCompiler`. `ModuleListCommand` (`packages/core/src/Commands/ModuleListCommand.php`) is the exact precedent — it constructor-injects `ModuleRepositoryInterface` and calls `$this->moduleRepository->all()`. NOTE: when `discovery:cache` runs, the cache is being (re)built, so boot itself rescanned (or used a stale cache); either way `ModuleRepositoryInterface->all()` returns the live resolved module list, which is what the compiler needs.
  - `DiscoveryCompiler::compile($modules)` returns the payload; `DiscoveryCache::write($payload)` persists it; count each section from the payload arrays for the report.
  - Write-failure contract: `DiscoveryCache::write()` THROWS `DiscoveryCacheException::notWritable` (Task 002) on any write failure. The command catches it and returns a non-zero exit code with `$e->getMessage()` (and suggestion) via `writeLine`. Do not rely on a boolean return.
- Test isolation: any test that writes a real cache file MUST use a UNIQUE per-test temp directory (point `ProjectPaths`/`DISCOVERY_CACHE_PATH` at it) and delete it in cleanup (mirror `appTestCleanupDirectory` in `packages/core/tests/Unit/ApplicationTest.php`). A leftover `storage/cache/discovery.php` under a shared base would silently flip later `Application::initialize()` tests onto the cache path.
- Standards: strict types, constructor promotion, no final, no magic methods, full type declarations. `DiscoveryCacheCommand` is in `marko/core` and MUST NOT reference `Marko\Config` (it injects `DiscoveryCache`/`DiscoveryCompiler`/`ModuleRepositoryInterface` only).

## Requirements (Test Descriptions)
- [ ] `it compiles discovery and writes the cache file, returning exit code 0`
- [ ] `it writes a cache file that DiscoveryCache reports as existing after the command runs`
- [ ] `it reports the cache file path and the counts of cached preferences, plugins, observers, and commands`
- [ ] `it returns a non-zero exit code and a helpful message (catching DiscoveryCacheException::notWritable) when the cache cannot be written`
- [ ] `it overwrites a pre-existing cache file with freshly compiled content`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
