# Plan: Scoped Entity Attributes (marko/scope + drivers)

## Created
2026-05-13

## Status
completed

## Objective
Introduce a three-package family (`marko/scope`, `marko/scope-mysql`, `marko/scope-pgsql`) that lets entities declare scoped attributes via `#[Scoped]`. Default values stay in their typed columns; overrides live in a JSON `scopes` column. Multi-axis support (declared-axis-priority precedence) is first-class. Repositories support ORDER BY on resolved scoped values. Axes and hierarchies are configured via `config/scope.php`, with `ScopeRegistryInterface` as the extension point for a future DB-driven definition store.

## Related Issues
none

## Discovery Notes

**Codebase state:** Greenfield for scopes. No existing scope mechanism on entities. `marko/config` has a flat single-axis tenant cascade — out of scope for this plan but conceptually aligned (uses `default + scopes` shape).

**Integration points:**
- `Marko\Database\Entity\Entity` (`packages/database/src/Entity/Entity.php`) — abstract class with a `companions()` mechanism. Scoped overrides attach as a `ScopedOverridesEntity` extender companion via the existing extender mechanism (`#[Table(extends: Parent::class)]`).
- `Marko\Database\Entity\EntityHydrator` — `hydrate()` already attaches extender companions automatically when extender columns are present in the row (lines 70-115). `extractAll()` already iterates companions for INSERT. No plugin or modification required.
- `Marko\Database\Repository\Repository` — `update()` already dirty-tracks companion columns and merges them into a single UPDATE (lines 632-700). No plugin or modification required. (Plugin-targeting `Repository::save` would not work — `Repository` is abstract and the plugin system does not walk parent class hierarchies.)
- `Marko\Database\Repository\Repository::matching(QuerySpecification ...)` — a `ScopedOrderBy` spec applies scope-aware ORDER BY via a driver-injected `ScopeSortRendererInterface`. Requires a new `orderByRaw()` method on `QueryBuilderInterface` (see tasks 029-031).
- `Marko\Database\Schema\Column` — string `type` field accepts `'json'`; PG's `PgSqlGenerator::TYPE_MAP` aliases `'json' → 'JSONB'`. Both driver helpers use `type='json'` for sibling-module symmetry.

**Naming convention confirmed:** `marko/scope-pgsql` (matches `marko/database-pgsql`), classes prefixed `MySql*`/`PgSql*` per `.claude/sibling-modules.md`.

**Key design decisions (resolved during clarification):**
- **Storage:** Default value lives in the entity's normal typed column. Only overrides live in a `scopes` JSON/JSONB column. Adding `#[Scoped]` to an existing entity is non-disruptive (no data migration; existing column unchanged). The column lives on the same table as the parent entity (extender pattern, mirroring `TimestampsExtender`).
- **JSON shape:** `{"axis:path": {"property": value, ...}, ...}` — keyed by scope key first, property second. No `default` key (defaults are real columns). Empty overrides = column `NULL`.
- **Multi-axis precedence:** Declared-axis-priority. `#[Scoped(axes: ['geo', 'locale'])]` walks axes in order; first axis with any matching override (including explicit `null`) at the current scope or any ancestor wins. Within an axis, deeper paths beat shallower.
- **Persistence:** `ScopedOverridesEntity` extender entity per parent — one-line subclass with `#[Table(extends: Parent::class)]`. Hydration / insert / update flow through the existing extender pipeline — no plugins.
- **API:** Plain reads via `$product->name` (typed column). Resolved reads via `ScopeResolver` service: `$resolver->resolved($product, 'name')` (current context) or `$resolver->resolvedAt($product, 'name', $scope)`. Mutations via `$resolver->setOverride()` / `clearOverride()`. No magic methods, no Entity base class changes.
- **Sort:** `ScopedOrderBy` QuerySpecification emits `ORDER BY COALESCE(scopes->'axis:path'->>'prop', ..., column)` via driver-specific renderer. Inline COALESCE — no generated columns in v1. Requires `orderByRaw()` on `QueryBuilderInterface` (added in this plan as a cross-cutting change to `marko/database`).
- **Axis config:** `config/scope.php` returning `['axes' => [...]]`. Modules contribute, resolver merges. Validation at boot.
- **Extension point:** `ScopeRegistryInterface` with `PhpScopeRegistry` as default impl. Future DB-driven impl is a swap of binding — interface unchanged.

## Scope

### In Scope
- `marko/scope` interface package with: `ScopeAxis`, `Scope`, `ScopeHierarchy`, `ScopeRegistryInterface`, `PhpScopeRegistry`, `ScopeContext`, `#[Scoped]` attribute, `ScopeMetadata`/`Factory`, `ScopedOverridesEntity` extender base class, `ScopedDataSerializer`, `ScopeWalker`, `ScopeResolver`, `ScopedEntityValidator`, `ScopeSortExpression` + `ScopeSortRendererInterface`, `ScopedOrderBy` QuerySpecification, `ScopedOrderByFactory`, loud-error exception family, module bindings.
- `marko/database` cross-cutting: `orderByRaw()` on `QueryBuilderInterface` and `RepositoryQueryBuilder`.
- `marko/database-mysql`, `marko/database-pgsql`: `orderByRaw()` implementations on the respective query builders.
- `marko/scope-mysql`: `MySqlScopeSortRenderer`, MySQL auto-migration integration test (locking in that `MigrationGenerator` emits the `scopes JSON` column via the existing extender pipeline), module bindings.
- `marko/scope-pgsql`: `PgSqlScopeSortRenderer`, PostgreSQL auto-migration integration test (locking in that `MigrationGenerator` emits the `scopes JSONB` column via the existing extender pipeline + `PgSqlGenerator::TYPE_MAP`), module bindings.
- READMEs for all three new packages per code-standards.md Package README Standards.
- TDD coverage with Pest, ≥80% line coverage.

### Out of Scope
- Filtering by resolved scoped value (planned follow-up).
- Scope-level uniqueness constraints.
- Generated columns / indexed scoped queries.
- Custom (runtime-defined) attributes — future `marko/eav` plan.
- `marko/config` integration (separate plan; existing flat scope cascade keeps working).
- `marko/scope-locale` or other sugar axes.
- Admin UI / scope hierarchy management UI.
- Cross-cutting documentation updates in `architecture.md` Package Inventory (the doc-updater pipeline agent handles this post-implementation).

## Success Criteria
- [ ] All three packages installable independently via Composer; drivers pull `marko/scope` transitively.
- [ ] An entity can declare `#[Scoped(axes: ['geo'])] public string $name;`, save it, reload it, and resolve it via `ScopeResolver` with the current `ScopeContext`.
- [ ] Multi-axis attributes resolve via declared-axis-priority across `ScopeContext`.
- [ ] Reading an entity with no `scopes` JSON returns the column value untouched (zero-overhead default path).
- [ ] `ScopedOrderBy` QuerySpecification produces correct `COALESCE(...)` SQL on both MySQL and PostgreSQL.
- [ ] `ScopeRegistryInterface` is swappable; replacing the binding with an alternative impl works without changes to the rest of the package.
- [ ] Unknown axes, unknown scope paths, and config-shape errors raise loud, actionable exceptions.
- [ ] All tests passing; ≥80% line coverage on every new package.
- [ ] Linter (`./vendor/bin/phpcs`) clean.
- [ ] Each package has a README per project standards.

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Bootstrap `marko/scope` package skeleton | - | completed |
| 002 | `Scope` + `ScopeAxis` value objects | 001 | completed |
| 003 | `ScopeHierarchy` (per-axis tree, walkUp/exists/isAncestor) | 001 | completed |
| 004 | Scope exception family | 001 | completed |
| 005 | `#[Scoped]` attribute | 001 | completed |
| 006 | `ScopeRegistryInterface` + `PhpScopeRegistry` | 002, 003, 004 | completed |
| 007 | `ScopeContext` (request-scoped singleton, mutable) | 002, 004, 006 | completed |
| 008 | `ScopeMetadata` + `ScopeMetadataFactory` (reflection on `#[Scoped]`) | 005, 006 | completed |
| 009 | `ScopedDataSerializer` (JSON shape + type conversion) | 002, 004 | completed |
| 010 | `ScopedOverridesEntity` base class (entity extender) | 002, 009 | completed |
| 011 | `ScopeWalker` (multi-axis declared-priority resolution) | 003, 006, 007, 010 | completed |
| 012 | `ScopeResolver` service (resolved/setOverride/clearOverride) | 008, 010, 011 | completed |
| 013 | `ScopedEntityValidator` (boot-time integrity check) | 008, 010 | completed |
| 014 | Save-path integration tests for `ScopedOverridesEntity` | 010, 012, 013 | completed |
| 015 | `ScopeSortExpression` + `ScopeSortRendererInterface` | 002, 008 | completed |
| 016 | `ScopedOrderBy` QuerySpecification | 008, 015, 029 | completed |
| 017 | `marko/scope` `module.php` bindings + singletons | 006, 007, 015, 032 | completed |
| 018 | Bootstrap `marko/scope-mysql` package skeleton | 001 | completed |
| 019 | `MySqlScopeSortRenderer` (JSON_UNQUOTE/JSON_EXTRACT COALESCE) | 015, 018 | completed |
| 020 | MySQL auto-migration integration test for `ScopedOverridesEntity` | 010, 018 | completed |
| 021 | `marko/scope-mysql` `module.php` | 019, 030 | completed |
| 022 | Bootstrap `marko/scope-pgsql` package skeleton | 001 | completed |
| 023 | `PgSqlScopeSortRenderer` (jsonb path COALESCE) | 015, 022 | completed |
| 024 | PostgreSQL auto-migration integration test for `ScopedOverridesEntity` | 010, 022 | completed |
| 025 | `marko/scope-pgsql` `module.php` | 023, 031 | completed |
| 026 | `marko/scope` README | 001..017, 032 | completed |
| 027 | `marko/scope-mysql` README | 018..021, 030 | completed |
| 028 | `marko/scope-pgsql` README | 022..025, 031 | completed |
| 029 | Add `orderByRaw` to `QueryBuilderInterface` and `RepositoryQueryBuilder` (marko/database) | - | completed |
| 030 | Implement `orderByRaw` on `MySqlQueryBuilder` (marko/database-mysql) | 029 | completed |
| 031 | Implement `orderByRaw` on `PgSqlQueryBuilder` (marko/database-pgsql) | 029 | completed |
| 032 | `ScopedOrderByFactory` service | 016 | completed |
| 033 | `HasScopesInterface` + `HasScopes` trait (storage primitives) | - | completed |
| 034 | Update `ScopeWalker` to accept `HasScopesInterface` | 033 | completed |
| 035 | Update `ScopeResolver` for trait-based entities | 033, 034 | completed |
| 036 | Update `ScopedEntityValidator` for trait-based entities | 033 | completed |
| 037 | Update `marko/scope` README for `HasScopes` trait | 033, 034, 035, 036 | completed |
| 038 | Remove `ScopedOverridesEntity` — single-approach cleanup | 033, 034, 035, 036, 037 | completed |

## Architecture Notes

### Storage shape
```sql
CREATE TABLE products (
  id BIGINT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,         -- default value, typed, indexable, FK-able
  scopes JSONB                         -- overrides only; NULL when none. Lives on the same table as the parent — column added by ProductScopedOverrides extender.
);

-- A row with a German override
id=2, name='Shirt',
  scopes = {"geo:eu.de": {"name": "Hemd"}}
```

The `scopes` column is contributed by the `ScopedOverridesEntity` subclass via `#[Table(extends: Product::class)]` and `#[Column(name: 'scopes', type: 'json', nullable: true)]`. The `SchemaRegistry` merges the extender's columns into the parent's table at schema-build time (see `packages/database/src/Schema/SchemaRegistry.php`).

### JSON shape (overrides only — no `default` key)
```json
{
  "geo:eu":     {"name": "Shirt-EU"},
  "geo:eu.de":  {"name": "Hemd"},
  "locale:de-DE": {"name_label": "Hallo"}
}
```

### Multi-axis precedence (declared-axis-priority)
```php
#[Scoped(axes: ['geo', 'locale'])]
public string $name;
```
Walker order at runtime with `ScopeContext::in('geo', 'eu.de')->in('locale', 'de-DE')`:
1. Axis `geo` — walk `eu.de → eu → (root)`. If any has `name`, return it.
2. Axis `locale` — walk `de-DE → de → (root)`. If any has `name`, return it.
3. No override anywhere → return the column value (default).

### API surface
```php
// One-time setup per scoped entity (in user code)
#[Table(extends: Product::class)]
class ProductScopedOverrides extends ScopedOverridesEntity {}

// Read
$product->name;                                        // column value (typed string)
$resolver->resolved($product, 'name');                 // current ScopeContext
$resolver->resolvedAt($product, 'name', $scope);       // explicit scope

// Write
$product->name = 'New Default';                        // sets column
$resolver->setOverride($product, 'name', 'Hemd', $scope);
$resolver->clearOverride($product, 'name', $scope);    // inherit again
$productRepo->save($product);                          // persists overrides via extender pipeline

// Query (factory pattern — ScopedOrderBy needs renderer + metadata + context)
$repo->matching($scopedOrderByFactory->create(Product::class, 'name', 'asc'));
```

### Extension point for DB-driven scopes
`ScopeRegistryInterface` is the only contract callers depend on. `PhpScopeRegistry` (loaded from `config/scope.php`) is the default binding. A future `DatabaseScopeRegistry` lives in a separate package, requires `marko/scope`, and re-binds the interface — no changes elsewhere.

### Persistence strategy (extender-based, no plugins)
- The override container is implemented as a normal `Entity` extender (`ScopedOverridesEntity`). App developers declare one companion class per scoped parent entity, e.g.:
  ```php
  #[Table(extends: Product::class)]
  class ProductScopedOverrides extends ScopedOverridesEntity {}
  ```
- Hydration: `EntityHydrator::hydrate` already attaches extender companions when their columns are present in the row (`packages/database/src/Entity/EntityHydrator.php` lines 70-115). No hydrate plugin needed.
- Insert: `Repository::insert` calls `EntityHydrator::extractAll` which already iterates companions. No insert plugin needed.
- Update: `Repository::update` already dirty-tracks companion columns and merges them into a single UPDATE (`Repository.php` lines 632-700). No update plugin needed.
- The `marko/database` cross-cutting changes are limited to adding `orderByRaw()` so `ScopedOrderBy` can emit a `COALESCE(...)` expression in the ORDER BY clause.

Rationale: the previously-planned `Repository::save` plugin would not have fired — the `marko/core` plugin system does not walk parent class hierarchies. `Repository` is `abstract` and user repositories are concrete subclasses, so `PluginRegistry::getEffectiveTargetClass` would have ignored the plugin's target. Routing through `RepositoryInterface` was rejected because the interface-wrapper interceptor strategy hides custom-repository methods (`findByEmail`, etc.).

### Sibling-module conformance
Both `marko/scope-mysql` and `marko/scope-pgsql` follow `.claude/sibling-modules.md`:
- Class prefix: `MySql*` / `PgSql*`.
- Identical public method names across drivers.
- Anonymous-class test pattern for connection-dependent code where applicable.
- `MySqlScopeSortRenderer` and `PgSqlScopeSortRenderer` are concrete; preferable to readonly when stateless.

## Risks & Mitigations

- **Dirty tracking of JSON column** — Handled by `EntityHydrator::getDirtyProperties` for free since `ScopedOverridesEntity::$scopes` is a normal `#[Column(type: 'json')]` property. No custom dirty flag needed.
- **JSON column missing on tables that need it** — `ScopedEntityValidator` (task 013) catches missing extenders at boot time with a loud `ScopeConfigurationException`. Migration-helper tasks (020/024) make adding the column trivial.
- **Multi-axis declared-priority subtleties** — developers may expect "deepest wins across axes." Mitigation: documented prominently in README with a worked example showing the resolution path. Sort emitters produce SQL that matches PHP walker behavior, asserted by parallel walker/SQL tests.
- **Cross-driver SQL drift between MySQL and PG renderers** — same expression must produce identical resolution. Mitigation: feature tests assert byte-identical resolution semantics by walking the same JSON in both renderers' output via integration tests where feasible; otherwise share a renderer-contract test suite.
- **Future filter/uniqueness work fights the v1 design** — the sort-only API may not generalize. Mitigation: `ScopeSortExpression` is a value object decoupled from the renderer; the filter equivalent (`ScopeFilterExpression`) can land alongside it later without retrofitting.
- **`Repository::insertBatch` rejects entities with companions** — scoped entities will always have a companion attached, so batch insert breaks. Acceptable for v1; a follow-up plan can either add batch-insert support for companions in `marko/database` or document the limitation.
- **`ScopeContext` is a mutable singleton** — in long-running PHP processes (FPM worker reuse, queue daemons), the bootstrap layer MUST call `clearAll()` between requests/jobs. Documented in the class docblock and README. Apps are responsible for the reset; failing to do so leaks scope state across requests.
