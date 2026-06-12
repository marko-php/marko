# Task 018: SQL generator type-map parity across mysql/pgsql

**Status**: complete
**Depends on**: [none]
**Retry count**: 0

## Description
The two schema SQL generators have non-overlapping abstract-type maps, so the same entity column type produces valid DDL on one driver and invalid (or absent) DDL on the other:
- `uuid` and `enum` map ONLY on pgsql (`UUID`, `VARCHAR`). On mysql they fall through (and a literal `UUID` is not a valid MySQL column type anyway).
- `bool`, `tinyint`, and `blob` map ONLY on mysql. On pgsql `TINYINT`/`BLOB` are invalid DDL, and the `bool`/`blob` aliases are absent (pgsql has `boolean`/`binary` but not those aliases).
- `decimal` is `DECIMAL(10,2)` on mysql but bare `DECIMAL` (unconstrained, defaults to arbitrary precision) on pgsql.

Add cross-driver mappings so every shared abstract type generates valid DDL on BOTH generators, with consistent `decimal` precision.

## Description-note
True modularity means siblings must accept the same abstract type vocabulary and emit each driver's correct native type. The Marko abstract types are the contract; each generator translates. This is the "sibling modules" parity rule applied to the schema layer.

## Context
- Related files (PATH CORRECTION — they live under `src/Sql/`, NOT `src/Schema/`):
  - `/Users/markshust/Sites/marko/packages/database-mysql/src/Sql/MySqlGenerator.php` (`TYPE_MAP` const ~29-49; `mapType()` ~302)
  - `/Users/markshust/Sites/marko/packages/database-pgsql/src/Sql/PgSqlGenerator.php` (`TYPE_MAP` const ~31-49)
  - Tests: `/Users/markshust/Sites/marko/packages/database-mysql/tests/Sql/MySqlGeneratorTest.php` and `/Users/markshust/Sites/marko/packages/database-pgsql/tests/Sql/PgSqlGeneratorTest.php`
- Verified findings (source-confirmed — exact current maps):
  - MySQL `TYPE_MAP` HAS: `integer`/`int`→`INT`, `bigint`, `smallint`, `tinyint`→`TINYINT`, `string`→`VARCHAR`, `text`, `boolean`/`bool`→`TINYINT(1)`, `datetime`→`DATETIME`, `date`, `time`, `timestamp`, `decimal`→`DECIMAL(10,2)`, `float`, `double`, `blob`/`binary`→`BLOB`, `json`→`JSON`. MISSING: `uuid`, `enum`.
  - PgSQL `TYPE_MAP` HAS: `integer`→`INTEGER`, `bigint`, `smallint`, `string`→`VARCHAR`, `text`, `boolean`→`BOOLEAN`, `datetime`/`timestamp`→`TIMESTAMP`, `date`, `time`, `decimal`→`DECIMAL` (unconstrained), `float`→`REAL`, `double`→`DOUBLE PRECISION`, `json`→`JSONB`, `uuid`→`UUID`, `binary`→`BYTEA`, `enum`→`VARCHAR`. MISSING: `tinyint`, `bool` (alias), `blob` (alias), `int` (alias).
- Patterns to follow:
  - MySQL generator: add `uuid` (a valid MySQL representation — `CHAR(36)` is the conventional UUID storage; do NOT emit literal `UUID`) and `enum` (`VARCHAR`, matching pgsql's pragmatic enum→varchar). Keep existing keys.
  - PgSQL generator: add `tinyint` (→ `SMALLINT`, the nearest valid pg integer; pg has no `TINYINT`), `bool` alias (→ `BOOLEAN`), `blob` alias (→ `BYTEA`), and `int` alias (→ `INTEGER`) so the mysql-style aliases also resolve on pg.
  - Reconcile `decimal` precision: make both emit the SAME constrained precision (e.g. both `DECIMAL(10,2)`) so a `decimal` column has identical scale/precision on both drivers. Update the pgsql map (and any pgsql `DECIMAL` test expectation) accordingly.
  - These are `private const array TYPE_MAP` entries — edit the constants. If `enum` needs length handling it is out of scope; map to plain `VARCHAR` exactly as pgsql already does.
  - Run BOTH packages' generator test suites; the existing `MySqlGeneratorTest`/`PgSqlGeneratorTest` decimal expectations may need updating to the reconciled precision.

## Requirements (Test Descriptions)
For `MySqlGeneratorTest`:
- [x] `it generates a valid MySQL type for a uuid column`
- [x] `it generates a valid MySQL type for an enum column`
- [x] `it generates DECIMAL with the shared precision for a decimal column`

For `PgSqlGeneratorTest`:
- [x] `it generates a valid PostgreSQL type for a tinyint column`
- [x] `it generates a valid PostgreSQL type for a bool column`
- [x] `it generates a valid PostgreSQL type for a blob column`
- [x] `it generates DECIMAL with the shared precision for a decimal column`

## Acceptance Criteria
- Each shared abstract type (`uuid`, `enum`, `bool`, `tinyint`, `blob`, `decimal`) generates valid DDL on BOTH generators.
- `decimal` emits identical precision/scale on both drivers.
- No mysql output contains a literal `UUID` column type; no pgsql output contains `TINYINT` or `BLOB`.
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- MySQL `TYPE_MAP`: added `uuid` → `CHAR(36)` (conventional UUID storage in MySQL) and `enum` → `VARCHAR` (pragmatic, matching pgsql).
- PgSQL `TYPE_MAP`: added `int` → `INTEGER`, `tinyint` → `SMALLINT`, `bool` → `BOOLEAN`, `blob` → `BYTEA`; changed `decimal` from bare `DECIMAL` to `DECIMAL(10,2)` to match MySQL precision.
- Updated existing PgSQL test `it maps Column types to PostgreSQL data types` decimal expectation from `DECIMAL` to `DECIMAL(10,2)`.
