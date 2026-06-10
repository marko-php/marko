# Task 013: F10 — docs-vec hybrid search crashes on FTS hits + leaks raw PDOException

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
`VecSearch::search()` (the only `DocsSearchInterface` driver that does hybrid
FTS5 + vector RRF fusion) has two distinct correctness defects in the same
method:

1. **Fusion crashes on every FTS hit.** In the RRF fusion loop, the FTS branch
   writes the score directly onto the bucket key — `$fused[$key] = (float
   expr)` — and the next line does `$fused[$key]['chunk'] = $chunk`, attempting
   to write an array offset onto a `float`. Any query that returns ≥1 FTS
   candidate aborts. (PHP raises "Cannot use a scalar value as an array" and,
   even where coerced, the `['chunk']` key is never set, so the downstream
   `$entry['chunk']` access fails regardless.) The adjacent vec branch is the
   correct shape and the FTS branch must mirror it: write `['score']` onto the
   bucket and set `['chunk']`. Because virtually every real query produces at
   least one FTS hit, hybrid search is effectively always broken — it was never
   caught because the existing `search()` tests are all gated on
   `isSqliteVecAvailable()` and skip in CI, so the FTS-only path through
   `search()` is never exercised.

2. **Malformed FTS5 MATCH leaks a raw `PDOException`.** User search text is
   bound as a parameter (no SQL injection) but is parsed by SQLite's FTS5 MATCH
   grammar at query time. A lone `"`, a trailing `NEAR/`, or an unknown
   `column:` prefix raises an `fts5: syntax error` as a raw `PDOException`.
   `SearchDocsTool::handle()` catches ONLY `DocsException`, so the raw exception
   escapes the tool (and a web route would 500 and leak the SQLite error). The
   FTS5 query execution must be wrapped and converted to the documented
   `DocsException::searchFailed(...)`.

## Context
- **Gap-audit follow-up.** docs-vec was missed by the original Tier 2 sweep
  (F1–F9 covered core, errors, discovery, queue, mail, and database drivers);
  this is the docs search driver. New finding F10.
- Related files:
  - `/Users/markshust/Sites/marko/packages/docs-vec/src/VecSearch.php`
    - `search()` (~44-95): the RRF fusion loops.
      - FTS branch (~63-67) is the BUG:
        `$fused[$key] = ($fused[$key]['score'] ?? 0.0) + 1.0 / (self::RRF_K + $rank + 1);`
        then `$fused[$key]['chunk'] = $chunk;` — array offset onto a float.
      - Vec branch (~69-73) is the CORRECT shape to mirror:
        `$fused[$key]['score'] = ($fused[$key]['score'] ?? 0.0) + ...;`
        then `$fused[$key]['chunk'] ??= $chunk;`. Note the `??=` so an
        FTS-set chunk is not clobbered; the FTS branch should set `['chunk']`
        with `=` (it runs first) or `??=` (equivalent on first write).
    - `ftsSearch()` (~100-119): builds the `WHERE docs_fts MATCH :q` statement,
      binds `:q` (value-bound — not the issue), then `$stmt->execute()` (~116)
      and `$stmt->fetchAll()` (~118) — this is where a malformed MATCH raises
      `PDOException`. Wrap the execute/fetch path and convert to
      `DocsException::searchFailed(...)`.
  - `/Users/markshust/Sites/marko/packages/docs/src/Exceptions/DocsException.php`
    — factory is `DocsException::searchFailed(string $reason): self`
    (VERIFIED: single `$reason` parameter). Reuse it; do NOT add a new factory
    unless a more specific one is warranted (prefer reusing `searchFailed`).
  - `/Users/markshust/Sites/marko/packages/mcp/src/Tools/SearchDocsTool.php`
    — `handle()` catches ONLY `DocsException` (VERIFIED ~55); proves the raw
    `PDOException` currently escapes and a route would 500.
  - `/Users/markshust/Sites/marko/packages/docs/src/ValueObject/DocsQuery.php`
    — `readonly` with `public string $query, public int $limit = 10`.
  - `/Users/markshust/Sites/marko/packages/docs/src/ValueObject/DocsResult.php`
    — `readonly` with `pageId, title, excerpt, score`.
- **Test infrastructure to follow (VERIFIED).**
  `/Users/markshust/Sites/marko/packages/docs-vec/tests/Unit/VecSearchTest.php`
  already has two file-level helpers:
  - `buildTestIndex(VecRuntime $runtime): array` → returns `[$search, $tempDir]`,
    building a real temp-dir SQLite index. It uses `HybridIndexBuilder` when a
    model is available, else falls back to `buildFtsOnlyIndex(...)`.
  - `buildFtsOnlyIndex(VecRuntime, MarkdownRepository, string $indexPath): void`
    — builds a real FTS5 (`docs_fts`) + `docs_meta` schema with NO vec table and
    NO embedding model. `VecRuntime::isModelAvailable()` gates whether vec
    results are produced.
  **Critical for these tests:** the existing `search()` tests are skipped via
  `->skip(fn () => ! (new VecRuntime(...))->isSqliteVecAvailable(), ...)`, which
  is exactly why the FTS-fusion crash was never caught. The NEW tests for this
  task MUST exercise the FTS-only path and MUST NOT be gated on
  `isSqliteVecAvailable()` — they should build an FTS-only index (or use a
  small in-process `:memory:`/temp FTS5 fixture) so they run deterministically
  in `composer test` even with no sqlite-vec extension and no model. This is the
  whole point: prove the FTS path through `search()` works.
- Patterns to follow:
  - Fix the FTS branch to mirror the vec branch exactly: accumulate into
    `$fused[$key]['score']` and set `$fused[$key]['chunk']`. Do NOT change the
    RRF formula (`1.0 / (RRF_K + rank + 1)`) or the `uasort`/`array_slice` top-N
    logic — only the bucket assignment shape.
  - Wrap ONLY the FTS5 MATCH execution (`execute()`/`fetchAll()` in
    `ftsSearch()`, or the call site) in `try { ... } catch (PDOException $e) {
    throw DocsException::searchFailed(...); }`. The `@throws DocsException` tag
    on `search()` already documents this; add/confirm `@throws DocsException` on
    `ftsSearch()` if the catch lives there. Import `PDOException` with a `use`
    statement (no inline FQCN).
  - Loud error: the converted exception message must include the FTS5 parse
    reason (e.g. `$e->getMessage()`) so the failure is diagnosable, per the
    loud-errors principle.
  - Do NOT broaden the catch to `Throwable` (that would swallow the index-open
    `DocsException` re-throw and other legitimate failures) — catch
    `PDOException` specifically.

## Requirements (Test Descriptions)
- [ ] `it returns ranked DocsResult objects for a query that produces one or more FTS hits without erroring`
- [ ] `it combines FTS and vector RRF scores into a single bucket and sets the chunk for an FTS-only entry`
- [ ] `it still searches successfully on the FTS-only path when no embedding model is available`
- [ ] `it throws DocsException not PDOException when the search text is a malformed FTS5 MATCH expression`
- [ ] `it includes the underlying FTS5 parse reason in the DocsException message`
- [ ] `it returns an empty list for a valid query that matches no documents`
- [ ] `it preserves the existing DocsException when the index file is missing (does not convert it twice)`

## Acceptance Criteria
- All requirements have passing tests
- The new FTS-path tests run (not skip) under `composer test` without sqlite-vec
- A malformed FTS5 MATCH surfaces as `DocsException`, caught by `SearchDocsTool`
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
