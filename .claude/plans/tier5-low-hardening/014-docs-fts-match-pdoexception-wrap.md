# Task 014: docs-fts malformed MATCH leaks PDOException

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
`FtsSearch::search()` binds the user query value via `bindValue(':q', ...)` (so there is no SQL injection), but a malformed FTS5 `MATCH` expression — e.g. an unbalanced quote or a bare `NEAR(` — makes SQLite throw a raw `PDOException` out of `$stmt->execute()`. The method is documented `@throws DocsException`, so a `PDOException` escaping it breaks the contract. Wrap the `execute()` (and the `fetchAll()` consumption) in a `try/catch` that converts the `PDOException` to `DocsException::searchFailed(...)`.

## Description-note
Loud errors must stay within the package's own exception type so callers can catch `DocsException` and not leak the underlying PDO driver exception. The value is already bound, so this is purely about converting an unexpected-but-possible runtime exception into the documented domain exception.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/docs-fts/src/FtsSearch.php` (`search()` ~29-57 — the `$stmt->execute()` at the MATCH query; method already declares `@throws DocsException`)
  - `/Users/markshust/Sites/marko/packages/docs/src/Exceptions/DocsException.php` (`searchFailed(...)` factory EXISTS at ~21 — reuse it; do NOT add a new factory)
  - Tests: `/Users/markshust/Sites/marko/packages/docs-fts/tests/` (locate the existing `FtsSearchTest.php` and extend it)
- Verified findings (source-confirmed):
  - `search()` prepares the MATCH query, `bindValue(':q', $query->query, PDO::PARAM_STR)`, `bindValue(':limit', ...)`, then `$stmt->execute()` with NO try/catch. `getPage()` already throws `DocsException::pageNotFound()`, confirming `DocsException` is the package's domain exception.
  - `DocsException extends MarkoException`; the `searchFailed` static factory is already defined.
- Patterns to follow:
  - Wrap `$stmt->execute();` (and the subsequent `fetchAll()` loop, since SQLite can defer the FTS error to fetch time) in `try { ... } catch (PDOException $e) { throw DocsException::searchFailed(...); }`.
  - Pass the original `PDOException` through as the `previous` exception if `searchFailed()` accepts it; otherwise include the offending query and the PDO message in the `message`/`context`. Confirm `searchFailed()`'s exact signature before wiring (read the factory).
  - `use PDOException;` at the top (the file already `use PDO;`). Keep `@throws DocsException` on `search()` accurate.

## Requirements (Test Descriptions)
- [ ] `it throws DocsException when the MATCH expression is malformed`
- [ ] `it does not leak a PDOException from a malformed search query`
- [ ] `it returns results for a valid search query`

## Acceptance Criteria
- A malformed FTS5 MATCH query surfaces as `DocsException` (via `searchFailed`), never as a raw `PDOException`.
- A valid query is unaffected and still returns `DocsResult` rows.
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
