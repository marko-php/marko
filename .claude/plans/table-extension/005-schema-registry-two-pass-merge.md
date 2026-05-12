# Task 005: `SchemaRegistry` two-pass merge & conflict detection

**Status**: completed
**Depends on**: 004
**Retry count**: 0

## Description
Convert `SchemaRegistry::registerEntities()` from a single-pass loop into a two-pass registration: pass 1 parses all entity classes (using the cached factory) and separates parents from extenders; pass 2 builds each parent's `Table` via `SchemaBuilder`, then merges columns/indexes/foreign-keys from each linked extender into that `Table` (via the existing `withColumn` / `withIndex` / `withForeignKey` immutable helpers). After merging, the parent's `EntityMetadata` is updated via `EntityMetadataFactory::linkExtenders()` (from Task 004) so the factory's cache also reflects the linking — any later `parse()` call returns the linked metadata. Detect column-name conflicts AND index-name conflicts at merge time and throw a loud error pointing at both classes.

Foreign-key merge: extender columns with `references:` produce FKs via `SchemaBuilder::buildForeignKeys()`. The two-pass merge must include them; otherwise extender FKs are silently dropped. Build FKs from the extender's metadata (so the FK name uses the parent's table name — verify `buildForeignKeys()` produces the correct `fk_{parentTable}_{column}` name when invoked with parent's tableName).

Keep single-entity `registerEntity()` working for the common case (no extenders); document that calling it on an extender alone is an error (the parent must be registered too — and since `registerEntities()` handles ordering, application code should use that).

## Context
- Related files:
  - `packages/database/src/Schema/SchemaRegistry.php` (modify)
  - `packages/database/src/Exceptions/EntityException.php` (add conflict exception)
  - `packages/database/tests/Schema/SchemaRegistryTest.php` (add or extend)
- Patterns to follow:
  - `Schema\Table::withColumn()` / `withIndex()` immutable mutators
  - `EntityMetadata::withExtenders()` from Task 002

## Requirements (Test Descriptions)
- [x] `it registers a parent entity alone with no extenders`
- [x] `it registers a parent entity with one extender and merges columns into the parent table`
- [x] `it registers a parent entity with multiple extenders and merges columns from all`
- [x] `it merges extender indexes into the parent table`
- [x] `it preserves the parent's primary key in the merged table`
- [x] `it links extender class-strings into the parent's EntityMetadata extenders field`
- [x] `it throws EntityException when two extenders add a column with the same name`
- [x] `it throws EntityException when an extender adds a column with the same name as a parent column`
- [x] `it throws EntityException when registering an extender whose parent class was not registered`
- [x] `it handles registration order independence (extender registered before parent)`
- [x] `it does not register the extender as its own Table (no duplicate tables in registry)`
- [x] `it merges extender foreign keys into the parent table`
- [x] `it throws EntityException when two extenders declare an index with the same name`
- [x] `it updates the EntityMetadataFactory cache so that a subsequent parse(parentClass) returns metadata with extenders populated`
- [x] `it handles registration order independence when an extender appears before its parent in the input array`
- [x] `it includes a discovered extender from EntityDiscovery in the merged table (regression test for discovery integration)`

## Acceptance Criteria
- All requirements have passing tests
- `SchemaRegistry::getTables()` after registering parent+extender returns one table with merged columns
- `SchemaRegistry::getMetadata($parentTable)` returns metadata with `$extenders` populated
- `SchemaRegistry::getEntityClass($parentTable)` returns the parent class (not an extender)
- Errors include both class-strings in conflict messages (both for column-name and index-name conflicts)
- `EntityMetadataFactory::parse(parentClass)` after `registerEntities()` returns metadata with `$extenders` populated (cache coherence)
- Property-name collisions between parent and extender are allowed (different classes are independent) — no error
- Code follows code standards
