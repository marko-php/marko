# Task 003: DiscoveryCompiler — produce the cache payload from a fresh scan

**Status**: pending
**Depends on**: [002]
**Retry count**: 0

> PREREQUISITE: This plan must land AFTER `tier2-high-correctness/003-classfileparser-tokenizer` is merged. The compiler bakes the scan result into a file; if the parser silently skips files, the cache permanently bakes in those omissions. See `_plan.md` Prerequisite note.

## Description
Add `DiscoveryCompiler`, which runs the four existing attribute-marker discovery passes (`PreferenceDiscovery`, `PluginDiscovery`, `ObserverDiscovery`, `CommandDiscovery`) over a resolved list of modules and returns the plain-array payload that `DiscoveryCache::write()` persists. It reuses the existing discovery classes unchanged so the compiled output is semantically identical to a live boot scan; it only maps each discovered value object to its array form.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/core/src/Discovery/DiscoveryCompiler.php` (NEW)
  - `/Users/markshust/Sites/marko/packages/core/tests/Unit/Discovery/DiscoveryCompilerTest.php` (NEW)
  - `/Users/markshust/Sites/marko/packages/core/src/Container/PreferenceDiscovery.php`, `Plugin/PluginDiscovery.php`, `Event/ObserverDiscovery.php`, `Command/CommandDiscovery.php` (REUSED — do not modify)
  - `/Users/markshust/Sites/marko/packages/core/src/Discovery/ClassFileParser.php` (constructor dependency for Observer/Command discovery)
  - `/Users/markshust/Sites/marko/packages/core/src/Application.php` (`initialize()` lines ~155-167 show how each discovery is constructed and called — mirror exactly)
- Patterns to follow:
  - `Application::discoverPreferences/Plugins/Observers/Commands` — how each discovery object is instantiated. EXACT current construction:
    - `PreferenceDiscovery` — `new PreferenceDiscovery()` (no args), `discoverInModule($manifest)` per module (note: `PreferenceDiscovery` and `PluginDiscovery` each `new ClassFileParser()` INTERNALLY per call — they take no constructor args).
    - `PluginDiscovery` — `new PluginDiscovery()` (no args), `discoverInModule($manifest)` per module.
    - `ObserverDiscovery` — in `Application` it is resolved via `$this->container->get(ObserverDiscovery::class)` and called `discover($this->modules)` (whole module list at once). It is a `readonly` class taking a `ClassFileParser`. The compiler MAY `new ObserverDiscovery(new ClassFileParser())` directly — no container needed, since no preference/plugin applies to it and the result is identical.
    - `CommandDiscovery` — `new CommandDiscovery($classFileParser)`, called `discover($this->modules)` (whole module list at once).
  - Because `ObserverDiscovery`/`CommandDiscovery` take the module LIST while `PreferenceDiscovery`/`PluginDiscovery` take a single module, the compiler must loop modules for the latter two and pass the full list to the former two — mirroring `Application` exactly, preserving module order (do not sort or dedupe).
  - Test setup `createTestModule` + temp `src/` fixtures in `packages/core/tests/Unit/Module/ModuleDiscoveryTest.php` for building module fixtures with attribute-bearing classes.
- Standards: strict types, constructor promotion, no final, no magic methods, full type declarations. Payload keys must match `DiscoveryCache` exactly (`version`, `preferences`, `plugins`, `observers`, `commands`).

## Requirements (Test Descriptions)
- [ ] `it compiles an empty payload (empty preference, plugin, observer, command arrays) for modules with no attribute-bearing classes`
- [ ] `it includes the current cache schema version key in the compiled payload`
- [ ] `it compiles preferences discovered across all modules into the payload preferences array`
- [ ] `it compiles plugins, observers, and commands discovered across all modules into their respective payload arrays`
- [ ] `it produces a payload that, written and reloaded through DiscoveryCache, yields discovery objects identical to running the four discovery passes directly over the same modules`
- [ ] `it aggregates results from multiple modules preserving the module load order`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
