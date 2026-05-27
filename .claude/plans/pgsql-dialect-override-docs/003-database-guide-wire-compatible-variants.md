# Task 003: Database Guide — Wire-Compatible Variants Section

**Status**: completed
**Depends on**: 002
**Retry count**: 0

## Description
Add a new section to `docs/src/content/docs/packages/database.md` titled "Wire-compatible database variants" that explains the 5-binding split (1 wire connection + 4 dialect interfaces) and walks through a worked CockroachDB example showing how a variant package depends on `marko/database-pgsql`, inherits `PgSqlConnection`, and overrides the 4 dialect interfaces via a `boot` callback in its own `module.php`.

## Context
- **Audience:** Someone who wants to add support for a postgres-wire-compatible (or mysql-wire-compatible) database. They land on `database.md` looking for the contract and the extension story.
- **The 5-binding split to document:**
  - `ConnectionInterface` — wire protocol (PDO connection, PostgreSQL/MySQL DSN). Inherited from the parent driver.
  - `SqlGeneratorInterface` — dialect (DDL generation for schema diffs).
  - `IntrospectorInterface` — dialect (reading existing schema from `information_schema` etc.).
  - `QueryBuilderInterface` — dialect (SELECT/INSERT/UPDATE/DELETE SQL generation).
  - `QueryBuilderFactoryInterface` — dialect (constructs query builders).
- **Worked CockroachDB example** — a complete `composer.json` + `module.php` for a hypothetical `marko/database-cockroachdb`:
  - `composer.json` requires `marko/database-pgsql` (which transitively requires `marko/database`).
  - `module.php` has no static `bindings` for the 4 dialect interfaces; instead a `boot` closure rebinds them to CockroachDB implementations.
  - One-line comment on why `ConnectionInterface` is not in the boot closure (PgSqlConnection reused because cockroach speaks the pg wire protocol).
- **Reference, don't duplicate:** Link to `/docs/concepts/dependency-injection/#overriding-another-modules-bindings` for the mechanism. This section's job is the *database-specific application*, not re-explaining DI. The anchor is produced by task 002's H2 "Overriding another module's bindings" via github-slugger and is locked.
- **Section anchor (locked):** The new H2 must be exactly `## Wire-compatible database variants`. github-slugger produces the anchor `wire-compatible-database-variants`. Task 004 hardcodes this anchor — do not change the title.
- **Do not invent a real CockroachDB driver:** The example shows the wiring shape only. Use stub class names like `CockroachDbGenerator` without claiming they exist.

## Requirements (Test Descriptions)
*Docs-only task — "tests" are content-quality assertions verified by review.*

- [ ] `the new section is titled "Wire-compatible database variants" (exact title — drives the anchor used by task 004)`
- [ ] `the section enumerates the 5 bindings and labels which is wire and which 4 are dialect`
- [ ] `the section includes a complete CockroachDB-style composer.json snippet requiring marko/database-pgsql`
- [ ] `the section includes a complete module.php snippet showing a boot closure rebinding the 4 dialect interfaces`
- [ ] `the section explains why ConnectionInterface is not rebound`
- [ ] `the section cross-links to /docs/concepts/dependency-injection/#overriding-another-modules-bindings for the underlying mechanism`

## Acceptance Criteria
- New section added to `docs/src/content/docs/packages/database.md` in a sensible position (likely after the existing driver overview).
- `npm --prefix docs run build` passes with no new warnings.
- All code examples are valid PHP and use correct namespaces from the actual `marko/database` package.
- No claim that any specific variant package exists or is officially supported.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
