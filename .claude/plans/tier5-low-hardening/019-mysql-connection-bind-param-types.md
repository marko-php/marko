# Task 019: MySQL connection binds bool/null/int as strings

**Status**: complete
**Depends on**: [none]
**Retry count**: 0

## Description
`MySqlConnection::query()`/`execute()` bind parameters by passing the bindings array straight to `PDOStatement::execute($this->prepareBindings($bindings))`. `prepareBindings()` only JSON-encodes array values; every other value is bound through `execute()`, which treats ALL parameters as `PDO::PARAM_STR`. So a PHP `false` is bound as `''`, `true` as `'1'`, `null` as `''`/`'0'` depending on context — which errors on INT/BOOL columns in MySQL strict mode. The pgsql sibling does NOT do this: its `bindValues()` selects an explicit `PDO::PARAM_BOOL`/`PARAM_NULL`/`PARAM_INT`/`PARAM_STR` per value. Bind explicit PDO param types in the mysql connection to match pgsql's behavior.

## Description-note
Sibling driver packages must behave identically for the same input. pgsql already binds with correct PDO types; mysql silently coerces booleans/nulls to strings. The fix brings mysql to parity by binding each value with its appropriate `PDO::PARAM_*` type.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/database-mysql/src/Connection/MySqlConnection.php` (`query()` ~119-131 and `execute()` ~134-146 — both call `$statement->execute($this->prepareBindings($bindings))`; `prepareBindings()` ~158-174 ONLY JSON-encodes array values, no PDO type binding; `prepare()` ~178-187 returns a `MySqlStatement`)
  - REFERENCE (the correct pattern): `/Users/markshust/Sites/marko/packages/database-pgsql/src/Connection/PgSqlConnection.php` (`bindValues()` ~187-213 — loops bindings, JSON-encodes arrays then binds `PDO::PARAM_STR`, else `match(true) { is_bool => PARAM_BOOL, is_null => PARAM_NULL, is_int => PARAM_INT, default => PARAM_STR }` and `$statement->bindValue($param, $value, $type)`; `query()` ~140-148 / `execute()` ~156-164 call `$this->bindValues($statement, $bindings)` then `$statement->execute()`)
  - Tests: `/Users/markshust/Sites/marko/packages/database-mysql/tests/` (locate the existing connection test; SQLite-in-memory is the typical harness for these — confirm how the existing mysql connection tests bind/exercise PDO)
- Verified findings (source-confirmed):
  - MySQL `prepareBindings()` body is exactly: `foreach ($bindings as $key => $value) { if (!is_array($value)) continue; try { $bindings[$key] = json_encode(...); } catch (JsonException $e) { throw ConnectionException::invalidArrayBinding(...); } } return $bindings;`. It never sets a PDO param type — so `execute($array)` binds everything as a string.
  - PgSQL `bindValues($statement, $bindings)` selects explicit param types via the `match(true)` shown above. THIS is the parity target.
- Patterns to follow:
  - Introduce a `bindValues(StatementInterface|PDOStatement $statement, array $bindings): void` (mirror pgsql's signature/visibility exactly — same name, same `private`) on the mysql connection that: JSON-encodes array values (folding in the existing `prepareBindings` array-handling so `ConnectionException::invalidArrayBinding` is still thrown), then `bindValue($param, $value, $type)` with the same `match(true)` type selection pgsql uses.
  - Change `query()`/`execute()` to `$this->bindValues($statement, $bindings); $statement->execute();` — mirroring pgsql, NOT passing the array to `execute()`.
  - Keep array-binding JSON-encoding behavior and the `ConnectionException::invalidArrayBinding` path intact (do not regress that fix).
  - Match sibling conventions precisely (method name, visibility, ordering) per `.claude/sibling-modules.md`. Do NOT change the public `ConnectionInterface` signature.
  - Note: PDO param keys may be positional (`?`) or named; mirror exactly how pgsql derives the `$param` (1-based index for positional). Read pgsql's `bindValues` loop fully before writing.

## Requirements (Test Descriptions)
- [x] `it binds a false boolean as a boolean not an empty string`
- [x] `it binds a true boolean correctly`
- [x] `it binds a null value as SQL NULL`
- [x] `it binds an integer value as an integer`
- [x] `it still JSON-encodes array bindings and throws on un-encodable arrays`

## Acceptance Criteria
- Binding `false`/`true`/`null`/int values through the mysql connection produces the correct DB values, matching pgsql behavior (no boolean→`''` coercion).
- Array values are still JSON-encoded, and an un-encodable array still throws `ConnectionException::invalidArrayBinding`.
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- Replaced `prepareBindings()` (which only JSON-encoded arrays) with `bindValues(PDOStatement, array)` mirroring the pgsql sibling exactly.
- `query()` and `execute()` now call `$this->bindValues($statement, $bindings); $statement->execute();` instead of `$statement->execute($this->prepareBindings($bindings))`.
- `bindValues()` uses 1-based positional param index (`$key + 1`) for integer keys, matching pgsql and PDO convention. This corrects the pre-existing test that asserted `'0'` as the param in error messages — updated to `'1'`.
- Array handling (JSON-encode + `PARAM_STR`) and `ConnectionException::invalidArrayBinding` path preserved intact.
- `PDOStatement` import added; `JsonException` import already present.
