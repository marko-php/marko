# Task 008: JSON Column Type

**Status**: complete
**Depends on**: none
**Retry count**: 0

## Description
Support `#[Column(type: 'json')]` with automatic `json_encode` on save and `json_decode` on hydration. 1.0 supports `array` and `?array` entity property types. Arbitrary nesting is supported for free — PHP arrays nest to any depth and both `json_encode`/`json_decode` and MySQL `JSON` / PostgreSQL `jsonb` handle it natively. Both drivers must emit correct DDL (MySQL `JSON`; PostgreSQL `jsonb`, preferred over `json` for index support).

**Scope boundary — permanent, not temporary:** `type: 'json'` means "PHP array ↔ JSON object/array at the root." Top-level JSON scalars (`42`, `"hello"`, `true`) and the bare JSON literal `null` are out of scope forever. PHP `null` maps to SQL `NULL` (not to the JSON literal `null`). Users needing full-JSON-value semantics use a `text` column with manual encoding, or a future custom type-mapper extension (post-1.0). This keeps the rule one sentence: "a JSON column holds a PHP array." Nulls *inside* the array round-trip naturally — only the degenerate "whole column is literally `null`" case is excluded.

**Nullable support:** Property typed `?array` with `#[Column(nullable: true)]` maps PHP `null` ↔ SQL `NULL`. The property type and the `nullable` flag must agree; a mismatch (`array` property with `nullable: true`, or `?array` with `nullable: false`) throws at metadata-parse time with a descriptive message.

## Context
- Related files:
  - `packages/database/src/Attributes/Column.php` — `type` is already `?string`; no schema change needed, but document `'json'` as a reserved value
  - `packages/database/src/Entity/EntityHydrator.php` — add JSON hydration/dehydration branch in both `hydrate()` and `extract()`, and coordinate with `registerOriginalValues()` / `isDirty` comparison so dirty tracking compares arrays rather than JSON strings
  - `packages/database/src/Entity/SchemaBuilder.php` — emit correct DDL per driver
  - `packages/database/src/Diff/SchemaDiff.php` — detect JSON column changes correctly
  - MySQL and PostgreSQL driver DDL generation
- Patterns to follow: existing enum and DateTimeImmutable hydration branches in `EntityHydrator`.

## Requirements (Test Descriptions)
- [x] `it hydrates a JSON column value into a PHP array`
- [x] `it dehydrates a PHP array into JSON for save`
- [x] `it round-trips nested associative arrays correctly`
- [x] `it round-trips sequential arrays correctly`
- [x] `it stores null when the property is null and the column is nullable`
- [x] `it throws a descriptive exception when decoding invalid JSON from the database`
- [x] `it throws a descriptive exception when encoding a value that cannot be JSON-encoded`
- [x] `it emits MySQL JSON DDL type for #[Column(type: 'json')]`
- [x] `it emits PostgreSQL jsonb DDL type for #[Column(type: 'json')]`
- [x] `it marks the column as dirty only when the decoded value actually changes`
- [x] `it uses JSON_THROW_ON_ERROR flags on both encode and decode`
- [x] `it rejects attributes where property type is not array or ?array (compile-time/metadata-parse guard)`
- [x] `it round-trips unicode and utf8mb4 content correctly on MySQL`
- [x] `it hydrates SQL NULL to PHP null when property is typed ?array`
- [x] `it dehydrates PHP null to SQL NULL (not to the JSON literal "null") when property is typed ?array`
- [x] `it throws at metadata-parse time when property type and #[Column(nullable:)] disagree`
- [x] `it round-trips a deeply nested structure (at least 10 levels) without loss`
- [x] `it preserves null values WITHIN the array payload (e.g. {"middle_name": null}) through round-trip`

## Acceptance Criteria
- `EntityHydrator` handles JSON columns symmetrically to other typed columns.
- Dirty tracking compares decoded values (not raw JSON strings) to avoid false-positive updates from encoder whitespace differences.
- Schema diff engine correctly detects JSON columns as a distinct type.
- Integration tests on both MySQL and PostgreSQL round-trip real data.

## Implementation Notes

### Files changed

- `packages/database/src/Attributes/Column.php` — Added `nullable: ?bool` parameter to support explicit nullable flag for JSON mismatch detection.
- `packages/database/src/Entity/PropertyMetadata.php` — Added `columnType: ?string` field to carry the raw Column `type` attribute value (e.g. `'json'`) separately from the PHP type (e.g. `'array'`).
- `packages/database/src/Entity/EntityMetadataFactory.php` — Added two validations for `type: 'json'` columns: (1) PHP type must be `array` or `?array`, (2) `nullable` flag and PHP type nullability must agree. Propagates `columnType` to `PropertyMetadata`.
- `packages/database/src/Entity/EntityHydrator.php` — Added `decodeJson()` and `encodeJson()` private methods using `JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE`. Both `convertToPhpType()` and `convertToDbValue()` check `$propMeta->columnType === 'json'` before other type branches. Dirty tracking naturally compares decoded PHP arrays since `hydrate()` stores the decoded value as the original.
- `packages/database/src/Exceptions/EntityException.php` — Added three static factory methods: `invalidJsonFromDatabase()`, `invalidJsonEncode()`, `jsonColumnTypeMismatch()`, `jsonColumnNullableMismatch()`.
- `packages/database/tests/Entity/JsonColumnTest.php` — New test file with 16 unit tests covering all requirements.
- `packages/database-mysql/tests/Sql/MySqlGeneratorTest.php` — Added DDL test (MySQL `JSON` type already in TYPE_MAP).
- `packages/database-pgsql/tests/Sql/PgSqlGeneratorTest.php` — Added DDL test (PostgreSQL `JSONB` type already in TYPE_MAP).

### Design decisions

- `columnType` on `PropertyMetadata` is a simple `?string` pass-through from the Column attribute's `type` field, not a typed enum, keeping the pattern consistent with existing code.
- Dirty tracking requires no special handling because `hydrate()` already stores the decoded PHP array as the original value. Comparing `array === array` is correct by value.
- `JSON_UNESCAPED_UNICODE` is used on encode so unicode content stored in MySQL utf8mb4 columns round-trips cleanly without `\uXXXX` escapes.
- Null inside the array payload (`{"key": null}`) round-trips correctly — `json_decode` preserves it as PHP `null` within the returned array.
- MySQL `JSON` and PostgreSQL `JSONB` DDL types were already present in the respective TYPE_MAPs; no generator changes were needed.
