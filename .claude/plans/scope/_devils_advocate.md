# Devil's Advocate Review: scope

## Critical (Must fix before building)

### C1. Save plugin will NEVER fire on user repositories — plugin system does not walk parent class hierarchy (task 014)

`Marko\Database\Repository\Repository` is `abstract`. User code uses concrete subclasses (e.g. `AdminUserRepository extends Repository implements AdminUserRepositoryInterface`). `PluginRegistry::getEffectiveTargetClass()` checks the resolved class, then its **interfaces only** (via `class_implements`) — it does **not** walk parent classes. So `#[Plugin(target: Repository::class)]` will never match when the container resolves `AdminUserRepository::class`.

The right target is `Marko\Database\Repository\RepositoryInterface::class`. That interface is inherited by every concrete repository via Repository's `implements RepositoryInterface`, so `class_implements()` returns it, and the interface-wrapper interceptor strategy fires.

But there's a secondary problem: the interface-wrapper strategy only exposes methods declared on `RepositoryInterface`. Concrete repositories typically define extra methods (`findByEmail`, custom finders) and custom interfaces. Wrapping with a `RepositoryInterface` proxy hides those — callers typed to `AdminUserRepositoryInterface` would lose `findByEmail`. Confirmed by reading `InterceptorClassGenerator::generateInterfaceWrapperCode()` — it only generates methods from the named interface.

The cleanest fix: drop the save-plugin approach and use the **existing extender mechanism**. Make `ScopedOverrides` an `Entity` subclass with `#[Table(extends: Parent::class)]` and a `#[Column(type: 'json')] public ?array $data` property. Then `Repository::insert` (via `extractAll`) and `Repository::update` (via per-companion dirty tracking) handle persistence with zero plugin involvement. The companion is linked into `EntityMetadata::extenders` at schema-registry time.

This requires:
- A way to discover/link the override-companion as an extender of any entity that has `#[Scoped]` properties. Either (a) require the app developer to declare a sibling override entity per parent, or (b) auto-generate one at metadata time.

Resolution applied: rewrite task 014 to use the extender path instead of a save plugin. Add a dedicated task for auto-registering the override extender so the existing schema/save machinery picks it up.

### C2. `RepositoryQueryBuilder` / `QueryBuilderInterface` has no `orderByRaw()` method — `ScopedOrderBy` cannot apply a COALESCE expression (tasks 015, 016, 019, 023)

`QueryBuilderInterface::orderBy(string $column, string $direction)` and the MySQL/Pg implementations both pass `$column` through `quoteIdentifier()` (e.g. `MySqlQueryBuilder::buildOrderByClause()` line 858). There is no raw-order escape hatch. `EntityQueryBuilderInterface` is just a passthrough. The plan acknowledges this risk in task 016 ("if absent, fall back to extending its API via a small plugin") but does not actually solve it. A QuerySpecification that emits `ORDER BY COALESCE(...)` cannot be applied through the current builder.

Two viable fixes:
1. Add `orderByRaw(string $expression, string $direction): static` to `QueryBuilderInterface`, `RepositoryQueryBuilder`, and both driver builders. This is a `marko/database` cross-cutting change and brings security obligations (must reject `;`, `--`, `/*`).
2. Add a narrower `orderByScoped(ScopeSortExpression $expr): static` to `EntityQueryBuilderInterface` and have `RepositoryQueryBuilder` collaborate with the driver renderer. Less general but lower blast radius.

Either way, this is a marko/database change, not just a marko/scope addition. The plan needs explicit tasks for it and the matching MySQL/Pg driver implementations.

Resolution applied: added new tasks for `orderByRaw()` on `QueryBuilderInterface` plus matching MySQL/Pg/RepositoryQueryBuilder updates; updated task 016 to depend on them.

### C3. `EntityHydrator::hydrate` does not include unmapped columns in the row passed forward — but the After-plugin signature receives the original `$row` as an argument, so this is OK (task 013)

After re-reading `PluginInterception::runAfterPlugins`: the after method receives `($result, ...$arguments)` where `$arguments` is the ORIGINAL invocation arguments. So the plugin gets `(Entity $entity, string $entityClass, array $row, EntityMetadata $metadata)`. `$row` contains the raw `scopes` JSON because the row dictionary is passed through unfiltered — `EntityHydrator::hydrate` just iterates known properties from metadata. Confirmed not a blocker for the hydrate side.

However, task 013 must explicitly require the plugin method signature to match the After-plugin contract and return `Entity`. Currently the task only says "named `hydrate` with `#[After]`" which is ambiguous about return type and argument list.

Resolution applied: clarified task 013's plugin method signature and return-type requirement.

### C4. Override-extender (replacement for the save plugin) needs metadata wiring — the schema/extender link normally requires `#[Table(extends: Parent::class)]` plus a manual call to `linkExtenders()` from a discovery mechanism (new task)

Looking at `SchemaRegistry.php:177` and `EntityMetadataFactory::linkExtenders`, extenders are linked at schema-registry build time, not auto-discovered by entity reflection. The marko/scope package must hook into this discovery to register the auto-generated `ScopedOverrides` companion as an extender of the parent entity.

Options:
1. Require app developers to write a `ProductScopedOverrides extends ScopedOverridesBase` companion class per scoped entity. Verbose but explicit.
2. Synthesize the extender class at runtime via `eval()`-style class generation (like the plugin interceptor does). Less verbose but more magic.
3. Add a new attribute `#[ScopedEntity]` on the parent that signals the extender needs to be auto-attached, and have the `marko/scope` module participate in schema-registry init.

The cleanest is option 1: each scoped entity has a matching `Foo` + `FooOverrides` pair. The base class `ScopedOverridesEntity` (in marko/scope) provides the shared `scopes` column and the `ScopedOverrides`-style accessor surface; the user's subclass per entity adds the `#[Table(extends: ...)]`. This is exactly the existing extender pattern.

Resolution applied: dropped the save plugin task; added a base `ScopedOverridesEntity` class task and reorganized 010/013/014 around the extender pattern. App developers declare a one-line `{Entity}ScopedOverrides extends ScopedOverridesEntity` companion per scoped entity, similar to how `TimestampsExtender` already works.

### C5. SQL identifier validation in renderers will reject axis colon-path JSON keys (tasks 019, 023)

The renderer task acceptance criteria require "Identifier validation per code-standards.md § SQL Identifier Validation," which validates against `^[a-zA-Z_][a-zA-Z0-9_]*$`. But the renderer needs to embed `"geo:eu.de"` style keys in JSON path expressions (MySQL `$."geo:eu.de"` or PG `->'geo:eu.de'`). Colons and dots are not allowed by that pattern.

Fix: validate the **axis name** and **path segments** separately against the safe pattern, then compose the JSON key as `$axis . ':' . $path` after validation. The fallback **column** name and the property name must still pass `IdentifierValidator::isValidIdentifier()`.

Resolution applied: clarified validation strategy in tasks 019 and 023, plus added the same clarification to task 015's interface contract.

### C6. The cross-package JSON shape contract is implicit — three tasks (009, 010, 011, plus renderers) each manipulate the override map without a shared, formal definition (tasks 009, 010, 011, 015)

The plan's Architecture Notes show `{"axis:path": {"property": value}}` but the task files do not pin this down:
- Task 009: "deserializes valid JSON into a flat array structure" — what shape?
- Task 010: "stores an override keyed by property and scope" — `[$prop][$scopeKey]` or `[$scopeKey][$prop]`?
- Task 011: walks axes — needs the inverted shape to look up overrides by scope key fast.
- Renderers: emit `JSON_EXTRACT(scopes, '$."axis:path".prop')` or `scopes->'axis:path'->>'prop'`.

If task 010 stores `[$property][$scopeKey] = $value` for dirty tracking convenience but task 011 expects `[$scopeKey][$property]`, the walker breaks silently.

Resolution applied: added an explicit data-shape contract to tasks 009, 010, 011, 013, 015 referencing the canonical `{scopeKey: {property: value}}` shape from `_plan.md`. Tasks 010 and 011 now require lookups by scope key to be O(1) (so internal storage is keyed by scope key first, property second).

### C7. `ScopedOverrides` companion conflicts with `Entity::companions()` extractAll path — non-entity companions break extractAll (task 010)

`Entity::attachCompanion()` accepts any `Entity`, and `EntityHydrator::extractAll()` calls `$this->metadataFactory->parse($companion::class)` on every companion. If `ScopedOverrides` extends `Entity` but lacks the required `#[Table]` attribute, `EntityMetadataFactory::validateEntity()` throws `EntityException::missingTableAttribute`. That happens on every `Repository::insert()` for any entity with a `ScopedOverrides` companion attached.

This is moot if we adopt the extender approach (C1/C4), because then `ScopedOverrides` IS a proper entity with `#[Table(extends: ...)]`. If the original plugin-based design is kept, `ScopedOverrides` must not extend Entity (use a different storage mechanism, e.g. a WeakMap keyed by entity).

Resolution applied: task 010 now extends `Entity` with a proper `#[Table(extends: ...)]` (extender approach), aligning with C1 fix.

## Important (Should fix before building)

### I1. Plugin discovery: `marko/scope` and the driver packages have no `discovery` hook — plugins aren't auto-registered (tasks 013, 017)

`PluginDiscovery::discoverInModule` scans `$manifest->path . '/src'` for files containing `#[Plugin`. So as long as plugin classes live under `packages/scope/src/`, they're discovered automatically. Good. But `module.php` must mark the module as enabled (it is — `extra.marko.module: true`). Task 017's tests don't verify discoverability.

Resolution applied: added a discoverability check to task 013's acceptance criteria.

### I2. `ScopeContext` is mutable but task 017 registers it as a singleton — request-scoped mutable singleton in a long-lived process is a footgun (task 007, 017)

In CLI / queue worker / long-running PHP-FPM contexts, a mutable shared singleton can leak state across requests. The plan calls it "request-scoped" but the container itself doesn't have a notion of request scope.

This is a documentation/known-limitation concern more than a build blocker. Should be called out in the README (task 026) and the ScopeContext class docblock.

Resolution applied: added a requirement to task 007 to document the lifecycle expectation, and to task 026 README to surface the caveat.

### I3. `marko/scope` requires `marko/config` (per task 006) but composer.json requires only `marko/core` (task 001)

Task 001 says "it requires PHP ^8.5 and marko/core in composer.json" but task 006 says `PhpScopeRegistry` loads from `ConfigRepositoryInterface`. Either the dependency is missing or registry-construction must be optional. Workers building task 001 will set the dependency wrong.

Resolution applied: task 001 now requires `marko/core` and `marko/config`.

### I4. Multi-axis "declared-priority" semantics with mixed found/notfound is under-specified (task 011)

The plan says "first axis with any matching override (current scope or ancestor) wins." But what if axis A has no override anywhere in its walk, and axis B has an explicit `null`? Task 011 says "preserves an explicit null override and does not fall through it" — this is one axis. What about across axes: does axis A's lack-of-override fall through to axis B, or does an explicit null in B count as "found"?

The walker should: for each axis in declared order, walk its hierarchy; if ANY scope key in this axis's walk has the property set (even to null), return that — explicit null is a real value. Only if no scope key in the axis's entire walk has the property defined do we fall to the next axis.

Resolution applied: clarified task 011's requirements to include this cross-axis behavior with explicit acceptance test for the case.

### I5. Setting an override for an entity with no metadata yet — `setOverride` ordering (task 012)

Task 012: "sets an override via setOverride attaching a ScopedOverrides companion if missing." But if `ScopedOverrides` is now an extender entity (C4), simply `new`ing it requires knowing the parent entity's PK column to mirror — which doesn't exist before `Repository::save`. The extender pattern handles this via `RegisterOriginalValues` on insert. ScopeResolver must be resilient to attaching the companion before the parent is persisted.

Resolution applied: added requirement to task 012 covering attach-before-save semantics, and a test case for "set override on a new (unsaved) entity, then save once → both rows reflect the override."

### I6. README dependency-chain for task 026 lists tasks 001-017 explicitly — too rigid (task 026)

Task 026 depends on `001..017`. If task ordering shifts (which it will, per C1 and C2), the listed dependencies need updating. Better to express as "all marko/scope src tasks before README."

Resolution applied: updated dependency lists to reflect the new task set after C1/C2 restructuring.

### I7. Sibling-module type-name divergence: `'json'` (MySQL) vs `'jsonb'` (PG) in migration helpers (tasks 020, 024)

`Marko\Database\Schema\Column::type` is a string. The PG generator's `TYPE_MAP` aliases `'json' → 'JSONB'`. The MySQL generator does similar (need to verify). The plan uses `'json'` for MySQL and `'jsonb'` for PG — which makes the two helpers diverge for no benefit. Either:
- Both use `'json'` (let driver-specific generator pick the storage), OR
- Both use a brand-new constant like `Column::TYPE_JSON_DOCUMENT`.

This is small enough to be a Minor — keeping it as Important because sibling-modules.md explicitly warns against this kind of divergence.

Resolution applied: tasks 020 and 024 now both use `'json'` (relying on the PG generator's existing alias to JSONB) for consistency.

### I8. `Repository::matching()` re-creates `RepositoryQueryBuilder` on each call; `ScopedOrderBy` needs access to the renderer, metadata factory, and scope context — wiring through a constructorless spec is messy (task 016)

Task 016 says: "resolves renderer + metadata + context via container access provided by the spec's constructor params." That sentence is a thinly-veiled service locator. The clean pattern is: spec constructor takes `ScopeSortRendererInterface`, `ScopeMetadataFactory`, `ScopeContext` explicitly; app code constructs the spec via the container.

Most Marko query-specifications get instantiated via `new` at the call site. Forcing container resolution here is awkward. Alternatives:
- Pass a `ScopedOrderByFactory` (registered as a service) and have app code call `$factory->create('name', 'asc')`.
- Have the spec defer renderer resolution until `apply()` is called, pulling from the EntityQueryBuilderInterface (which doesn't have container access either).

The factory pattern is cleanest.

Resolution applied: split task 016 into two — one for the value-object spec (`ScopedOrderBy`) which takes its deps via constructor explicitly, one for a `ScopedOrderByFactory` service. Plan task table updated.

## Minor (Nice to address)

### M1. Terminology collision: `marko/config` already uses "scope" for tenant cascade

The plan explicitly calls this out as Out of Scope. Worth a one-liner in the marko/scope README to disambiguate: "Note: `marko/scope` is separate from the `scope` parameter on `ConfigRepositoryInterface`, which handles tenant-cascade config lookup."

### M2. Plugin sortOrder is not set on `#[After]` (task 013)

Defaults to 0. If another plugin also targets `EntityHydrator::hydrate` with sortOrder 0, `PluginRegistry::register` throws `conflictingSortOrder`. Not a likely real-world conflict but worth a defensive `sortOrder: 100` to leave room for app plugins.

### M3. Test-shape consistency: tasks 009 and 010 should share fixture builders

Both tasks construct override maps from scratch in tests. A small shared fixture helper would prevent shape drift.

### M4. `ScopedOrderBy` "no axes active in context" fallback to plain `orderBy` quietly degrades — should it instead error loudly?

Per loud-error philosophy, a `ScopedOrderBy('name')` in a context where no axes are set arguably should fail rather than silently return rows in default-column order. Counter-argument: it's reasonable that "no override context → use defaults."

## Questions for the Team

### Q1. Are app developers expected to declare a `FooScopedOverrides` companion class per scoped entity, or do we auto-generate one?

With the extender-based approach (post-fix), the cleanest user experience is declaring a one-line companion: `class ProductScopedOverrides extends ScopedOverridesEntity { /* #[Table(extends: Product::class)] */ }`. The alternative is runtime class generation (more magic, harder to debug, fights `loud errors / explicit over implicit`).

Recommend explicit declaration; the README example should show the pattern.

### Q2. Should the `scopes` column be added to the parent entity's table OR a separate table?

The plan and the C4 fix both put it on the parent table (extender pattern adds columns to the same table). Pros: no JOIN on read, simpler. Cons: tables for "narrow" entities (e.g. lookup tables) get fatter. The plan is consistent with Marko's existing `TimestampsExtender` pattern — likely the right call, but worth confirming.

### Q3. How do scope overrides interact with `Repository::insertBatch()`?

`Repository::insertBatch` rejects entities with companions (`BatchInsertException::companionsNotSupported`). After the C4 refactor, every scoped entity automatically has the `ScopedOverrides` companion — batch insert breaks for them. Is that acceptable for v1, or do we need a follow-up to thread overrides through batch insert?

### Q4. Should `marko/scope-mysql` work on MariaDB? MariaDB's JSON support differs slightly (JSON_VALUE, no JSON_TABLE etc.)

The MySQL renderer uses `JSON_UNQUOTE(JSON_EXTRACT(col, '$."key"'))` or the `->>` shortcut. Both are supported by MariaDB 10.3+. Worth one acceptance test against MariaDB or a documented minimum version.
