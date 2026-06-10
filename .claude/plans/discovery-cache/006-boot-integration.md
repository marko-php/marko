# Task 006: Boot integration — prefer cache outside development

**Status**: pending
**Depends on**: [001, 002]
**Retry count**: 0

> PREREQUISITE: This plan must land AFTER `tier2-high-correctness/003-classfileparser-tokenizer` is merged. See `_plan.md` Prerequisite note.

## Description
Wire `DiscoveryCache` + `DiscoveryEnvironment` into `Application::initialize()` so that, when the cache is enabled and the environment is not development and a valid cache file exists, boot hydrates the preference / plugin / observer / command registries from the cache instead of running the four filesystem scans. In development the cache is always ignored and the scans always run. A corrupt cache throws loudly (no silent fallback). The proof of value is that a cache hit performs zero per-file scanning.

**HARD CONSTRAINT — no `marko/config` in the boot path.** The cache gate reads
`DiscoveryEnvironment` (Task 001), which reads `$_ENV` directly. It MUST NOT resolve or
reference `ConfigRepositoryInterface` / anything in `Marko\Config`. Reason:
`ConfigRepositoryInterface` is bound lazily by `marko/config`'s `module.php` binding
closure, which only fires AFTER discovery (during the module `boot` callbacks at the end
of `initialize()`), and `marko/core` cannot depend on `marko/config` regardless. The
original plan's `DiscoveryConfig` (wrapping `ConfigRepositoryInterface`) is REMOVED — it
was a chicken-and-egg / inverted-dependency dead end.

**Env availability at gate time.** `initialize()` calls `EnvLoader` first (line ~114,
gated by `class_exists`), so `$_ENV` is populated before the gate reads it. When
`marko/env` is absent, `DiscoveryEnvironment` falls back to its documented defaults
(enabled, production) — which is the safe production default. Document this in a test.

## Description of refactor
Split each of `discoverPreferences/Plugins/Observers/Commands` into two halves: a "scan → definitions" half (existing discovery call) and a "register definitions into the registry" half. Boot then sources the definition lists from EITHER the live scan OR `DiscoveryCache::load()`, and runs the same registration half for both paths.

**Registry construction and container wiring that currently lives INSIDE the discover methods MUST run on BOTH paths** — it is easy to miss:
- `discoverObservers()` currently does `$this->observerRegistry = new ObserverRegistry()` BEFORE registering. The cache path must also construct the `ObserverRegistry`.
- `discoverCommands()` currently does `$this->commandRegistry = new CommandRegistry()`, then after registering also `$this->container->instance(CommandRegistry::class, ...)` and `$this->commandRunner = new CommandRunner($this->container, $this->commandRegistry)`. The cache path must construct the registry, register definitions, AND perform the identical container `instance()` binding + `CommandRunner` creation. Without the `CommandRegistry` container binding and `CommandRunner`, the CLI cannot run any command on a cache hit — a silent activation failure.
- `discoverPreferences()`/`discoverPlugins()` register into `$this->preferenceRegistry`/`$this->pluginRegistry` (constructed earlier at lines 137-138, unchanged).

The cleanest refactor: keep the four `discoverX()` methods doing scan-and-register (unchanged), and add four parallel `registerXFromCache(array $definitions)` (or a single hydrate-and-register path) that perform ONLY the registry construction + registration + container wiring halves, fed by `DiscoveryCache::load()`. The fork chooses which to call per subsystem.

All other boot wiring (module discovery, autoloaders, container/registry construction, bindings, event dispatcher creation, `discoverRoutes`, module `boot` callbacks) is unchanged. Only the source of the four definition lists changes. NOTE: `discoverRoutes` (RoutingBootstrapper) is NOT cached and still runs its own scan every request — that is in-scope-excluded and unchanged.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/core/src/Application.php` (`initialize()` lines ~111-186 and the four `discoverX()` methods, lines ~238-305)
  - `/Users/markshust/Sites/marko/packages/core/tests/Unit/ApplicationDiscoveryCacheTest.php` (NEW) (or extend the existing Application boot test)
  - `/Users/markshust/Sites/marko/packages/core/src/Discovery/DiscoveryCache.php` (Task 002), `Discovery/DiscoveryEnvironment.php` (Task 001)
- Patterns to follow:
  - The existing `discoverX()` methods register into `$this->preferenceRegistry` / `$this->pluginRegistry` / `$this->observerRegistry` / `$this->commandRegistry`. Preserve those exact registry calls AND the container `instance()` wiring (see Description of refactor) in the registration half.
  - Placement: the fork goes where the four `discoverX()` calls are today (lines ~157-174), AFTER `ProjectPaths` is registered (line 149) so `DiscoveryCache` can resolve its path, and BEFORE the event dispatcher / module repository / routes / module boot callbacks. Construct `$env = new DiscoveryEnvironment()` and `$cache = new DiscoveryCache($projectPaths, $env)` locally (or resolve from container).
  - Decision predicate: `$env->enabled() && $env->environment() !== 'development' && $cache->exists()`.
  - To prove "cache hit skips the scan", the test asserts no scanning occurs — e.g. point the modules at a `src/` fixture containing an attribute-bearing class that the cache deliberately OMITS, prime the cache, boot, and assert the omitted class is NOT registered (cache used verbatim, filesystem not consulted); contrast with a dev-mode boot of the same setup where the class IS registered (rescan). A scan-counter spy on `ClassFileParser`/discovery is an acceptable alternative if cleaner.
- Standards: strict types, constructor promotion, no final, no magic methods, full type declarations. The boot path MUST NOT reference `Marko\Config`.

## Requirements (Test Descriptions)
- [ ] `it hydrates preferences, plugins, observers, and commands from the cache when enabled and environment is not development and the cache exists`
- [ ] `it binds CommandRegistry in the container and creates the CommandRunner on a cache hit (commands are runnable without a scan)`
- [ ] `it does not consult the filesystem for markers on a cache hit (a class present on disk but absent from the cache is not registered)`
- [ ] `it always rescans the filesystem in development even when a cache file exists (a class present on disk is registered regardless of cache contents)`
- [ ] `it rescans the filesystem when caching is enabled but no cache file exists`
- [ ] `it rescans the filesystem when caching is disabled by env (DISCOVERY_CACHE_ENABLED=false)`
- [ ] `it throws DiscoveryCacheException during boot when the cache exists but is corrupt and caching is enabled outside development`
- [ ] `it produces the same registered preferences, plugins, observers, and commands from a valid cache as from a fresh scan of the same modules`
- [ ] `it rescans (defaults to enabled production) when marko/env is not installed and no env vars are set`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
