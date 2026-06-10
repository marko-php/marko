# Task 015: mcp read-only DB guard bypass via stacked statements

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
`QueryDatabaseTool::isAllowedPrefix()` checks ONLY the first whitespace-delimited token of the SQL (`strtok($sql, " \t\n\r")` upper-cased against an allowlist of `SELECT, WITH, SHOW, EXPLAIN, DESCRIBE`). A stacked statement like `SELECT 1; DELETE FROM users` passes the guard because its first token is `SELECT`, yet the second statement mutates data. Reject multiple/stacked statements (a `;` that terminates a statement, outside string literals) in addition to the existing first-token allowlist, keeping the `allowWrite=true` opt-in path unchanged.

## Description-note
This is a local stdio-only tool, so the severity is Low, but the read-only guard is the whole point of the no-`allowWrite` path — a trivial `;`-separated bypass defeats it. The fix tightens the guard without changing the tool's public arguments.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/mcp/src/Tools/Runtime/QueryDatabaseTool.php` (`isAllowedPrefix()` ~71-76; the `! $allowWrite` branch ~42-48 that calls it; `ALLOWED_PREFIXES` const)
  - Tests: `/Users/markshust/Sites/marko/packages/mcp/tests/` (locate the existing `QueryDatabaseToolTest.php` and extend it)
- Verified findings (source-confirmed):
  - `isAllowedPrefix()`: `$first = strtoupper(strtok($sql, " \t\n\r") ?: ''); return in_array($first, self::ALLOWED_PREFIXES, strict: true);`. Only the first token is inspected — stacked statements bypass.
  - The guard is consulted only when `! $allowWrite`; when `$allowWrite` is true the tool requires `confirm=true` and otherwise executes writes. That opt-in write path must be preserved.
- Patterns to follow:
  - Add a stacked-statement rejection: a statement separator `;` that is NOT inside a single- or double-quoted string literal means more than one statement. The simplest robust approach: scan the SQL char-by-char tracking quote state (`'`/`"`, honoring escaped/doubled quotes), and reject if a `;` is seen outside a string AND any non-whitespace, non-comment content follows it. A bare trailing `;` (e.g. `SELECT 1;`) with nothing after should be allowed.
  - Combine with the existing first-token allowlist: BOTH must pass for the read-only path. Return the existing not-permitted error response shape when either check fails (reuse the current `'content' => [['type' => 'text', 'text' => ...]], 'isError' => true` structure; the message may mention stacked statements).
  - Do NOT attempt a full SQL parser. A string-literal-aware `;` scan is sufficient and matches the loud-but-pragmatic intent. Keep `ALLOWED_PREFIXES` and the `allowWrite`/`confirm` behavior untouched.

## Requirements (Test Descriptions)
- [ ] `it rejects a stacked statement that starts with SELECT`
- [ ] `it allows a single SELECT statement`
- [ ] `it allows a single SELECT statement with a trailing semicolon`
- [ ] `it does not treat a semicolon inside a string literal as a statement separator`
- [ ] `it still permits write statements when allowWrite and confirm are both true`

## Acceptance Criteria
- `SELECT 1; DELETE FROM users` is rejected on the read-only path; a plain `SELECT ...` (with or without a trailing `;`) is allowed.
- A `;` inside a quoted string literal does not trip the stacked-statement guard.
- The `allowWrite=true` + `confirm=true` path still executes write statements.
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
