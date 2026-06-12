# Devil's Advocate Review: discovery-cache

Reviewed against current source on branch `feature/discovery-cache`. The tokenizer-based
`ClassFileParser::extractClassName` (the hard prerequisite) is **confirmed present and correct**
(`packages/core/src/Discovery/ClassFileParser.php` uses `token_get_all()`, not `preg_match`).
`marko/core/composer.json` confirms it requires only `php` + `psr/container` (no `marko/config`).
The four value objects, four discovery classes, and `Application::initialize()` structure all match
the plan's cited shapes.

## Critical (Must fix before building)

### C1 — Task 006 gate runs in the CLI boot too, and `discovery:cache`/`discovery:clear` would self-block on a stale/corrupt cache (activation + dead-lock trap)
`packages/cli/src/CliKernel.php::doRun()` calls `$app->initialize()` *before* dispatching ANY
command, including `discovery:cache` and `discovery:clear`. With Task 006's gate, the CLI's own
boot will read the cache. Two failure modes the plan does not address:

1. **Corrupt cache locks you out of the fix.** Task 006 makes a corrupt cache throw
   `DiscoveryCacheException` during `initialize()`. But `initialize()` is what runs *before*
   `discovery:clear` can execute. So the one command whose entire job is to remove a corrupt cache
   can never run — boot throws first. The loud-error policy is right, but it must not brick the
   recovery path.
2. **Stale cache silently feeds the recompile.** On a cache *hit*, the four registries are
   hydrated from the cache and the four scans are skipped — but `discovery:cache` recompiles from
   `ModuleRepositoryInterface->all()` + a fresh `DiscoveryCompiler` scan (Task 003/004), which is
   correct and independent of the hydrated registries. So the recompile itself is fine. The real
   problem is only #1 (corrupt cache) plus the conceptual surprise that the CLI always boots through
   the gate.

**Fix:** Task 006 must state that the gate runs during CLI boot as well (the same `initialize()`),
AND that `discovery:clear` must remain runnable when the cache is corrupt. The cleanest resolution:
the corrupt-cache `throw` happens at hydration time, and the CLI entrypoint already wraps
`initialize()` in `try/catch (Throwable)` (CliKernel lines 52-56) — but that catch turns the throw
into exit code 1 and the command never runs. So Task 006 must require that the gate's corrupt-cache
detection is **bypassed in development** (already specified) AND document the operator recovery
contract: a corrupt cache is cleared by deleting the file (`discovery:clear` cannot self-heal a
corrupt cache because boot precedes it). Add an explicit requirement/test:
`it throws a DiscoveryCacheException whose suggestion tells the operator to delete the cache file
(discovery:clear cannot run through a corrupt boot)`. Update Task 005 to note `discovery:clear` only
works when boot succeeds (cache valid or absent), so the corrupt-recovery story is "delete the
file" — and `DiscoveryCacheException`'s suggestion (Task 002) must therefore name the **file path
to delete**, not only `vendor/bin/marko discovery:clear`.

### C2 — `$_ENV` mutation in Task 006 tests will leak into the ~40 existing `initialize()` tests
`packages/core/tests/Unit/ApplicationTest.php` already contains ~40 tests that call
`$app->initialize()` against temp module fixtures (lines 98-557). Task 001 and Task 006 introduce a
gate that reads `$_ENV['APP_ENV']`, `$_ENV['DISCOVERY_CACHE_ENABLED']`, `$_ENV['DISCOVERY_CACHE_PATH']`.
Pest runs tests in parallel **per worker process**, and `$_ENV` is process-global. Any Task 001/006
test that sets `$_ENV['APP_ENV'] = 'production'` (to exercise the cache-hit path) without restoring it
will silently flip every *subsequent* `initialize()` test in the same worker into "cache enabled,
production" mode — and if a `storage/cache/discovery.php` happens to exist under that test's temp base,
those tests will hydrate from a cache instead of scanning, producing confusing, order-dependent
failures.

**Fix:** Add an explicit requirement to Tasks 001 and 006: every test that mutates `$_ENV` MUST
snapshot and restore the three keys in `beforeEach`/`afterEach` (or use `try/finally`), leaving
`$_ENV` exactly as found. State that the default (no env set) path must be exercised to prove the
existing boot tests are unaffected. This is a test-isolation contract, not optional polish — without
it the suite becomes flaky.

### C3 — Default `enabled=true` + `environment='production'` means EVERY existing `initialize()` test now takes the cache path by default whenever a cache file exists under the temp base
Task 001 specifies `enabled()` defaults to `true` and `environment()` defaults to `'production'`.
That means the gate predicate `enabled() && environment() !== 'development' && exists()` is satisfied
by default the moment a `discovery.php` exists at `{base}/storage/cache/`. The existing tests use a
temp base with no such file, so they're safe **today**. But Task 004's `discovery:cache` test and
Task 006's cache-hit tests will *write* a `discovery.php` under a temp base; if any of them reuse a
shared base path or don't clean up the file, a later `initialize()` test under the same base silently
switches to the cache path.

**Fix:** Require (Tasks 004, 006) that every test using a real cache file writes it under a
**unique per-test temp directory** and deletes it in cleanup (mirror `appTestCleanupDirectory` in
`ApplicationTest.php`). Also state in Task 006 that the gate's `$cache->exists()` is resolved against
`ProjectPaths::$base` (the temp base), so tests get isolation for free *as long as* the temp base is
unique and cleaned. Make this an explicit acceptance criterion, not an implementation detail.

### C4 — `discovery:cache` and `discovery:clear` are core `#[Command]`s, so they are discovered by `CommandDiscovery` — but `CommandDiscovery` scans `src/`, and these commands live in `src/Commands/`. Confirm the registry does not collide on a cache hit.
On a cache *hit* (Task 006), `discoverCommands()` is skipped and commands come from the cache file.
The cache was compiled (Task 003) by scanning `src/` — which includes `DiscoveryCacheCommand` and
`DiscoveryClearCommand` themselves (they carry `#[Command]`). Good: they end up in the cache, so
they remain runnable on a cache hit. But verify the **ordering**: `CommandRegistry::register()`
throws `CommandException::duplicateCommandName` on a duplicate. If the cache-hit path constructs a
fresh `CommandRegistry` and registers each cached `CommandDefinition` exactly once, there is no
collision. The risk is a refactor that registers BOTH from cache AND re-scans (double registration).

**Fix:** Task 006's `registerCommandsFromCache()` (or equivalent) MUST be mutually exclusive with
`discoverCommands()` — exactly one runs. Add a requirement:
`it registers each cached command exactly once and never throws duplicateCommandName on a cache hit`.
This guards the most likely refactor bug (calling both halves).

## Important (Should fix before building)

### I1 — Task 002's hydration must reject associative-array corruption that `var_export` round-trips silently, and the "missing required field" check needs to cover the nested plugin-method shape
`PluginDefinition::$beforeMethods`/`$afterMethods` are associative arrays keyed by target method,
values `['pluginMethod' => string, 'sortOrder' => int]` (verified in `PluginDefinition.php` and
`PluginDiscovery::parsePluginClass`). Task 002 already has a round-trip test for this. But the
"missing required field" validation (`it throws ... when a record within a section is missing a
required field`) currently names only `targetClass`. A hand-edited or version-skewed cache could
have a plugin entry whose `beforeMethods` value is missing `sortOrder`, or whose `aliases` got
coerced to a non-array. Hydration via `new PluginDefinition(...)` would then pass a malformed array
straight into the registry, surfacing as a confusing error deep in `PluginInterceptor` rather than a
loud `DiscoveryCacheException` at load time.

**Fix:** Broaden Task 002's malformed-record requirement to assert that each section validates its
record shape before constructing the value object: preferences need `replacement`+`replaces`;
plugins need `pluginClass`+`targetClass`+array `beforeMethods`/`afterMethods`; observers need
`observerClass`+`eventClass`+int `priority`+bool `async`; commands need `commandClass`+`name`+string
`description`+array `aliases`. Add a requirement:
`it throws DiscoveryCacheException when a record field has the wrong type (e.g. observer priority is
a string or command aliases is not an array)`.

### I2 — Task 003 equivalence test must construct discovery classes EXACTLY as `Application` does, but `Application` resolves `ObserverDiscovery` through the container (autowired) while the compiler `new`s it — the plan asserts these are equivalent but provides no guard
The plan's risk note acknowledges `Application` does
`$this->container->get(ObserverDiscovery::class)` whereas the compiler will `new
ObserverDiscovery(new ClassFileParser())`. They ARE equivalent today because no preference/plugin
targets `ObserverDiscovery`. But that equivalence is an *invariant the compiler silently depends on*.
If someone later writes a `#[Preference]` replacing `ObserverDiscovery`, `Application` would honor it
(container resolution) and the compiler would not (direct `new`), producing a cache that disagrees
with a live boot — exactly the bug this whole plan exists to avoid.

**Fix:** Task 003's equivalence test (`it produces a payload that ... yields discovery objects
identical to running the four discovery passes directly`) must compare against the **same
construction `Application` uses**, i.e. run the reference discovery for observers via the container
(or document loudly in `DiscoveryCompiler` that it intentionally bypasses preferences for discovery
classes and why that is safe). Add a one-line note to Task 003 and a code comment requirement so the
invariant is discoverable, not buried in `_plan.md`. This is the single most important correctness
guard in the plan.

### I3 — Task 006 refactor: the `CommandRegistry` container binding + `CommandRunner` creation currently lives at the END of `discoverCommands()` (lines 301-304). The cache path must replicate BOTH, and the event dispatcher + module repository are created BETWEEN observers and commands.
Verified against `Application.php`: `discoverObservers()` (line 163) → create `EventDispatcher` +
bind `EventDispatcherInterface` (166-167) → create `ModuleRepository` + bind
`ModuleRepositoryInterface` (170-171) → `discoverCommands()` (174). The command-registry container
binding (`$this->container->instance(CommandRegistry::class, ...)`) and `CommandRunner` creation are
at lines 302-304, INSIDE `discoverCommands()`. Task 006 already flags this, but it must be explicit
that the fork cannot simply wrap all four scans in one `if` — observers run BEFORE the event
dispatcher/module repository wiring, and commands run AFTER. The cleanest structure is **four
independent forks** (or four `registerXFromCache` methods) interleaved at the exact same positions as
today, NOT one monolithic branch around all four.

**Fix:** Reword Task 006's refactor section to require preserving the **exact interleaving**:
preferences (157) → plugins (160) → observers (163) → [dispatcher+module-repo wiring, unchanged] →
commands (174). Each subsystem chooses scan-vs-cache independently at its current call site. Add a
requirement: `it preserves the existing boot ordering (observers before event-dispatcher creation,
commands after module-repository binding) on both the scan and cache paths`.

### I4 — Task 001/006 env-coercion for `DISCOVERY_CACHE_ENABLED` is under-specified at the boundary that matters most: a present-but-unparseable value
Task 001 specifies `'0'`, `'false'`, `''` → false, "everything else truthy." But `$_ENV` values are
always strings (or absent). The dangerous case is a deploy that sets `DISCOVERY_CACHE_ENABLED=no` or
`=off` expecting it to disable the cache — under the spec, `'no'`/`'off'` are "everything else" →
**true**, so the cache stays enabled contrary to operator intent, and in production that means a
stale-cache-in-prod surprise. This is a production-safety footgun.

**Fix:** Either (a) explicitly document in Task 001 that ONLY `'0'`/`'false'`/`''` disable and all
other values (including `'no'`/`'off'`) enable — and make `discovery:cache`/the docs say so loudly —
or (b) expand the false-set to the conventional `{'0','false','no','off',''}` (case-insensitive).
Option (b) matches operator expectations and is the safer default. Pick one and pin it in the test:
`it treats DISCOVERY_CACHE_ENABLED values {0,false,no,off,empty} (case-insensitive) as disabled`.
(Recommend option b.)

### I5 — Task 004 reports "counts of cached preferences/plugins/observers/commands" but counts from the payload BEFORE write confirmation — and `var_export` of class-strings is fine, but the atomic-write `rename()` can fail silently on cross-device temp dirs
Task 002 specifies atomic write via temp-file-then-`rename()` "in the same directory." That is
correct and must stay (a temp file in the system temp dir then `rename()` across filesystems fails
with a warning and a `false` return that is easy to ignore). Task 004's "non-zero exit on write
failure" requirement depends on `DiscoveryCache::write()` actually surfacing the failure.

**Fix:** Task 002 must require `write()` to throw `DiscoveryCacheException::notWritable($path)` (not
return `false` / not emit a warning) when the directory cannot be created, the temp write fails, or
the `rename()` fails — so Task 004's command can catch it and exit non-zero with a real message. Add
to Task 002: `it throws DiscoveryCacheException::notWritable when the target directory cannot be
created or the file cannot be written`. Then Task 004's "helpful message when the cache cannot be
written" test asserts it catches that exception. Tie the two together explicitly so the failure
contract is not split across tasks with a gap.

### I6 — Task 003 dependency on Task 002 is for the schema-version constant only; flag the coupling so a parallel worker doesn't hardcode `version => 1`
Task 003 must emit `'version' => DiscoveryCache::CACHE_VERSION` in the payload (the plan says the
compiler "includes the current cache schema version key"). If Task 003's worker hardcodes `1` instead
of referencing the constant defined in Task 002, a future bump in Task 002 silently desyncs the
compiler from the loader and every freshly compiled cache fails its own version check on load.

**Fix:** Task 003 requirement `it includes the current cache schema version key in the compiled
payload` must specify the value comes from `DiscoveryCache::CACHE_VERSION`, not a literal. Add the
note to Task 003's standards line. (Dependency 003←002 already exists, so the constant is available.)

## Minor (Nice to address)

### M1 — `config/discovery.php` top-level key will be `discovery` (file basename), so config consumers read `discovery.enabled`, `discovery.cache_path`, `discovery.environment`
Confirmed via `ConfigDiscovery::mergeConfigFromDirectory` (`$key = pathinfo($file, FILENAME)`). Not a
defect — just worth noting in Task 001 so the CLI-consumer story (commands reading via
`ConfigRepositoryInterface`) uses the right dotted keys. No fix applied (informational).

### M2 — `CommandRegistry::all()` sorts alphabetically; cache order vs scan order won't matter for commands, but `preferences`/`plugins`/`observers` registration order CAN matter (conflict detection, sort order). The compiler preserves module order (good), and hydration should preserve array order (PHP arrays are ordered) — just confirm no `ksort`/`array_unique` sneaks into hydration.
Informational; Task 002's "don't turn associative arrays into lists" test partially covers this.

### M3 — The plan ships `config/discovery.php` as core's first `config/` dir. `marko/core/composer.json` does not list it anywhere (config is discovered by glob, not declared), so no composer change is needed — but the package-structure test (if any) that asserts core's file layout may need updating.
Worth a quick check during Task 001; not blocking.

## Questions for the Team

### Q1 — Should `discovery:cache` refuse to run (or warn) in development?
In development the cache is never read, so compiling it is a no-op for boot. Running `discovery:cache`
in dev silently writes a file that nothing consumes until `APP_ENV` changes. Harmless, but possibly
surprising. Should the command print a notice ("APP_ENV=development; this cache will not be used until
you deploy to a non-development environment")? Not specified. (Leaving as a question — no behavior
change applied.)

### Q2 — Is there a deploy/release hook that should auto-run `discovery:cache`?
The plan's stale-cache risk rests entirely on "document that `discovery:cache` must run on deploy."
`.claude/release-process.md` exists. Should this plan add a step there, or is that a separate docs
task owned by the doc-updater pipeline? (Out of scope for the task files; flagging for the team.)

### Q3 — `DiscoveryEnvironment` reads `$_ENV` at call time, not construct time. Confirm intended.
If `DiscoveryEnvironment` reads `$_ENV` in its getters (not the constructor), then a `module.php`
boot callback that mutates `$_ENV` after the gate has run cannot affect the already-made gate
decision (the gate runs before boot callbacks). That's the correct sequencing, but the plan should
confirm getters read live `$_ENV` so tests that set env before constructing it behave predictably.
Informational.
