# Task 029: Implement runtime MCP tools (run_console_command, query_database, read_log_entries, last_error, app_info)

**Status**: pending
**Depends on**: 022
**Retry count**: 0

## Description
Implement five runtime-bridge MCP tools that give AI agents live introspection of a running Marko application:
- `run_console_command` — execute a registered CLI command and return output
- `query_database` — run read-only SQL via `ConnectionInterface`
- `read_log_entries` — tail recent log lines
- `last_error` — return most recent uncaught exception or logged error
- `app_info` — PHP version, Marko version, DB engine, installed package versions, active drivers

## Context
- Namespace: `Marko\Mcp\Tools\Runtime\*`
- These tools require a bootstrapped Marko app context (run from inside the project)
- `query_database` MUST enforce read-only at the connection level, not via regex. Regex-based write-SQL rejection is leaky (stored procedures, `WITH ... UPDATE ...` CTEs, multi-statement). Implementation:
  - Introduce a `ReadOnlyConnection` wrapper (or use a read-only PDO mode where the driver supports it, e.g., PostgreSQL `default_transaction_read_only` session setting, MySQL `SET SESSION TRANSACTION READ ONLY`)
  - `query_database` resolves `ConnectionInterface` and opens a new connection with `readOnly: true` (or wraps the existing connection in `ReadOnlyConnection`)
  - A strict allowlist of SQL prefixes (SELECT, WITH, SHOW, EXPLAIN, DESCRIBE) is enforced as a secondary check before sending to the driver
  - Opt-in `allowWrite: true` argument bypasses both layers but triggers a loud warning in the response and requires confirmation via a separate `confirm: true` argument
  - `marko/database` may need a small addition: either a `readOnly` flag on `ConnectionInterface::connect()` or a `ReadOnlyConnection` decorator in `marko/database`'s public API. Document this clearly; if the addition to `marko/database` is material, spin out a sub-task.

## Requirements (Test Descriptions)
- [ ] `it registers run_console_command tool delegating to the CLI dispatcher`
- [ ] `it registers query_database tool using a read-only connection by default`
- [ ] `it enforces a SELECT/WITH/SHOW/EXPLAIN/DESCRIBE prefix allowlist as secondary defense`
- [ ] `it rejects write SQL even when the allowlist is bypassed because the connection itself is read-only`
- [ ] `it allows write SQL only when both allowWrite and confirm flags are set`
- [ ] `it returns a loud warning in the response body when allowWrite is used`
- [ ] `it registers read_log_entries tool returning last N entries from LoggerInterface-compatible source`
- [ ] `it registers last_error tool returning the most recent error with stack trace`
- [ ] `it registers app_info tool returning PHP Marko DB engine and package versions`
- [ ] `it handles each tool's failure mode with a loud error content block`

## Acceptance Criteria
- Destructive SQL rejection is strict — regex + keyword match
- `app_info` sources versions from composer's installed.json

## Implementation Notes
(Filled in by programmer during implementation)
