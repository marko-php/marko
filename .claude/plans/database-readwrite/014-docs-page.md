# Task 014: Docs Page (packages/database-readwrite.md)

**Status**: pending
**Depends on**: 011
**Retry count**: 0

## Description
Write `docs/src/content/docs/packages/database-readwrite.md` — the canonical reference page for the package. Covers installation, the nested config schema, replica strategy selection, sticky-write semantics, single-request fallback behavior, the `prepare()` policy, the long-running-process caveat, and cross-links to the DI override pattern doc.

## Context
- **File location:** `docs/src/content/docs/packages/database-readwrite.md`
- **Reference docs to mirror for tone and structure:** Read `docs/src/content/docs/packages/database.md` and `docs/src/content/docs/packages/database-pgsql.md` to match the existing docs voice (declarative, no marketing, code-forward).
- **Required sections (lock the headings as listed — they drive cross-links from other docs):**
  1. **Frontmatter** — `title: Database Read/Write Split`, `description: ...`, any other Starlight frontmatter consistent with sibling pages.
  2. **Overview** — 2-3 sentences: what it does (decorates ConnectionInterface to route reads/writes), when to use it (high-traffic apps with replicated databases), and that it's opt-in.
  3. **Installation** — `composer require marko/database-readwrite`. Note that it requires a base driver (pgsql or mysql) already installed.
  4. **Configuration** — full nested config example with both `random` and `weighted` shown. Document every key, including optional ones (`weight`, `read_strategy`).
  5. **How routing works** — table summarizing which methods route to which connection (query → replica via selector, execute/prepare/lastInsertId/transactions → writer).
  6. **Sticky-write behavior** — when the sticky flag activates (any write, any prepare, any beginTransaction), when it does NOT clear (commit/rollback do not reset), and how to clear manually (`resetStickyState()`).
  7. **Replica selection strategies** — Random (default, recommended), Weighted (with weights example), and a note that the `ReplicaSelectorInterface` is open for custom strategies via Preference (cross-link to DI concept doc).
  8. **Single-request fallback** — on PDOException from a replica, the same query is retried on the next replica; when all exhausted, `AllReplicasFailedException` is thrown. Note that writers never get fallback (only one writer exists). Note that this is NOT a circuit-breaker — for sustained replica failures, use ops-layer load-balancer health checks.
  9. **prepare() policy** — always routes to writer. Explain why (no SQL parsing, parameterized statements typically used for INSERT/UPDATE).
  10. **Long-running processes (caveat)** — sticky state auto-resets per request in PHP-FPM via singleton lifecycle. In long-running processes (Swoole, RoadRunner, queue workers, CLI commands), call `$readWriteConnection->resetStickyState()` between jobs to avoid sticky state leaking across boundaries.
  11. **Loud-error behavior** — list each Marko exception the package raises and what to do about it (missing write config, empty read array, bad weight, unknown strategy, all-replicas-failed).
  12. **Customization / Preferences** — how to swap the selector, how to swap the connection (less common). Cross-link to `concepts/dependency-injection.md#overriding-another-modules-bindings`.
  13. **API Reference** — concise list of public signatures: `ReadWriteConnection` constructor, `resetStickyState()`, `ReplicaSelectorInterface`, exception classes.
- **Cross-links to add elsewhere (NOT this task — doc-updater in post-implementation pipeline may handle):**
  - `packages/database.md` should gain a paragraph in or near the "Wire-compatible database variants" section pointing at the readwrite package as another opt-in extension.
  - The package inventory in `.claude/architecture.md` should grow a `Database Read/Write Split` row. Doc-updater may catch this.

## Requirements (Test Descriptions)
*Docs-only task — assertions are content-quality, verified by review.*

- [ ] `the docs page exists at the correct path and has Starlight-compatible frontmatter`
- [ ] `the configuration section shows the full nested config schema with both random and weighted examples`
- [ ] `the routing table covers all 6 ConnectionInterface methods and all 5 TransactionInterface methods`
- [ ] `the sticky-write section explains the activate-and-do-not-auto-clear semantics`
- [ ] `the section explicitly documents that commit/rollback do not clear sticky state`
- [ ] `the long-running-process section explains when to call resetStickyState()`
- [ ] `the prepare() policy section explains why it always routes to writer`
- [ ] `the fallback section explains AllReplicasFailedException and clarifies that the writer is never fallback target`
- [ ] `the customization section cross-links to /docs/concepts/dependency-injection/#overriding-another-modules-bindings`

## Acceptance Criteria
- New docs page at the named path.
- `npm --prefix docs run build` passes with no new warnings or broken-link errors.
- Tone matches sibling pages (declarative, no marketing).
- All code examples are valid PHP / valid config.
- Cross-link anchors resolve correctly.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
