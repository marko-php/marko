# Task 002: DiscoveryCache read/write/exists/clear with loud corruption errors

**Status**: pending
**Depends on**: [001]
**Retry count**: 0

> PREREQUISITE: This plan must land AFTER `tier2-high-correctness/003-classfileparser-tokenizer` is merged. See `_plan.md` Prerequisite note.

> Now depends on 001 because `DiscoveryCache`'s constructor takes `DiscoveryEnvironment` (for the cache path), NOT `DiscoveryConfig`/`ConfigRepositoryInterface`. `marko/core` must not reference `Marko\Config`.

## Description
Add `DiscoveryCache`, the component that owns the compiled cache file at `storage/cache/discovery.php`: it writes a given discovery payload to a deterministic PHP `return [...]` file, reports existence, removes the file, and loads + hydrates the file back into `PreferenceRecord` / `PluginDefinition` / `ObserverDefinition` / `CommandDefinition` objects. Add `DiscoveryCacheException` for loud failures. Corrupt, unreadable, malformed, or version-mismatched cache content throws — it never silently degrades.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/core/src/Discovery/DiscoveryCache.php` (NEW)
  - `/Users/markshust/Sites/marko/packages/core/src/Exceptions/DiscoveryCacheException.php` (NEW)
  - `/Users/markshust/Sites/marko/packages/core/tests/Unit/Discovery/DiscoveryCacheTest.php` (NEW)
  - `/Users/markshust/Sites/marko/packages/core/src/Container/PreferenceRecord.php` (`replacement`, `replaces`), `Plugin/PluginDefinition.php` (`pluginClass`, `targetClass`, `beforeMethods`, `afterMethods` — the latter two are ASSOCIATIVE arrays keyed by target method name, values `['pluginMethod' => string, 'sortOrder' => int]`), `Event/ObserverDefinition.php` (`observerClass`, `eventClass`, `priority` int, `async` bool), `Command/CommandDefinition.php` (`commandClass`, `name`, `description`, `aliases` array) — value object shapes to hydrate. ALL fields are scalars/arrays-of-scalars: confirmed serializable, no closures, no nested objects.
  - `/Users/markshust/Sites/marko/packages/core/src/Path/ProjectPaths.php` (`$base`)
  - `/Users/markshust/Sites/marko/packages/core/src/Discovery/DiscoveryEnvironment.php` (Task 001 — provides `cachePath()`)
- Patterns to follow:
  - Existing core exceptions in `packages/core/src/Exceptions/` (e.g. `CommandException`) — `extends MarkoException`, named static factory methods, three-part `message`/`context`/`suggestion`. Mirror that for `DiscoveryCacheException::unreadable($path)`, `::malformed($path, $reason)`, `::versionMismatch($path, $found, $expected)`, `::notWritable($path)`. The `suggestion` for corrupt/version cases MUST name the cache FILE PATH to delete AND `vendor/bin/marko discovery:clear` — but note the ordering: a corrupt cache throws during `Application::initialize()`, which runs BEFORE any command can dispatch (see `CliKernel::doRun()`), so `discovery:clear` cannot itself run through a corrupt boot. Deleting the named file is therefore the primary recovery instruction; `discovery:clear` is the convenience path once boot succeeds. Make the path the first, most prominent part of the suggestion.
  - `ProjectPaths` for resolving a relative `cache_path` against `$base`; use an absolute `cache_path` as-is. Detect absolute via a leading `/` (or a Windows drive prefix) — do NOT assume relative.
  - Serialize via `var_export($payload, true)` wrapped as `"<?php\n\nreturn " . ... . ";\n"` with a leading "generated — do not edit, run discovery:clear to remove" comment.
  - Cache schema `version` constant on the class (e.g. `DiscoveryCache::CACHE_VERSION = 1`); the written payload's `'version'` key uses it.
  - Write atomically (write to a temp file in the SAME directory as the target, then `rename()`) so a concurrent boot never reads a half-written file. Do NOT use the system temp dir for the temp file — a cross-filesystem `rename()` fails. A partially written cache that fails the array/keys/version checks must throw, not be silently used.
  - `write()` MUST surface failures by throwing `DiscoveryCacheException::notWritable($path)` — never return `false` and never let a PHP warning be the only signal — when the target directory cannot be created, the temp write fails, or the `rename()` fails. Task 004's command relies on catching this to exit non-zero with a real message.
  - Record-shape validation on load: each section validates its record shape BEFORE constructing the value object, throwing `DiscoveryCacheException::malformed()` on the first bad record. Required fields per section: preferences → `replacement` (string), `replaces` (string); plugins → `pluginClass` (string), `targetClass` (string), `beforeMethods` (array), `afterMethods` (array); observers → `observerClass` (string), `eventClass` (string), `priority` (int), `async` (bool); commands → `commandClass` (string), `name` (string), `description` (string), `aliases` (array). A missing key OR a wrong-typed value is malformed. This keeps corruption loud at load time instead of leaking a malformed array into `PluginInterceptor`/registries.
- Standards: strict types, constructor promotion (`ProjectPaths`, `DiscoveryEnvironment`), no final, no magic methods, full type declarations. NO reference to `Marko\Config`.

## Requirements (Test Descriptions)
- [ ] `it writes a discovery payload to a return-array PHP file at the configured cache path and creates the directory if missing`
- [ ] `it reports exists() true after a write and false before any write or after clear`
- [ ] `it clears the cache file and clear() is idempotent when the file is already absent`
- [ ] `it loads a written cache back into PreferenceRecord, PluginDefinition, ObserverDefinition, and CommandDefinition objects identical to the payload`
- [ ] `it round-trips PluginDefinition beforeMethods/afterMethods associative arrays preserving target-method keys and the pluginMethod/sortOrder shape`
- [ ] `it round-trips empty definition arrays and empty aliases/beforeMethods/afterMethods without turning associative arrays into lists`
- [ ] `it resolves a relative cache_path against the project base path and uses an absolute cache_path unchanged`
- [ ] `it throws DiscoveryCacheException when the cache file content is not a PHP array`
- [ ] `it throws DiscoveryCacheException when the cache file is missing required keys (version, preferences, plugins, observers, commands)`
- [ ] `it throws DiscoveryCacheException when the cache version key does not match the current cache schema version`
- [ ] `it throws DiscoveryCacheException when a record within a section is missing a required field (e.g. a plugin entry without targetClass)`
- [ ] `it throws DiscoveryCacheException when a record field has the wrong type (e.g. observer priority is a string, command aliases is not an array, plugin beforeMethods is not an array)`
- [ ] `it throws DiscoveryCacheException::notWritable when the target directory cannot be created or the file/rename cannot be written (never returns false silently)`
- [ ] `it throws DiscoveryCacheException whose suggestion names the cache file path to delete as the primary recovery, plus discovery:clear, when content is corrupt`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
