# Task 000: Audit live `search_docs` retrieval for greenfield questions

**Status**: completed
**Depends on**: none
**Retry count**: 0

> **Investigation/validation task — not a Red-Green-Refactor code task.** Its deliverable is a documented query→result matrix and a go/no-go verdict, not production code or tests. The orchestrator/worker should treat it as verification: run the queries, record results, write findings into this file's Implementation Notes.

## Description
Confirm the design assumption that depth lives in the MCP: that the live `marko-mcp` `search_docs` tool actually **ranks the right doc into its top results** for natural greenfield phrasings a developer building an app on Marko would use. The plan pushes Bucket-C ("how X works") content to `search_docs` instead of inlining it; this task verifies that bet against retrieval quality, not just content existence.

## Context
- **Content coverage already confirmed** (pre-audit, from the repo): the indexed corpus `packages/docs-markdown/docs/` contains `concepts/{modularity,dependency-injection,plugins,preferences,events}.md`, `tutorials/custom-module.md` (279 lines), `getting-started/*`, and per-package `guides/*`. The gap risk is **ranking**, not missing docs — e.g. the literal phrase "create a module" appears in 0 files although `custom-module.md` is exactly that.
- The `marko-mcp` server may not be connected in every session — if absent, register/run it per `packages/docs-markdown` + `packages/mcp` setup (or run the `search_docs` driver directly against the docs index) before auditing. Note in findings which path was used.
- Driver behind `search_docs`: `marko/docs-fts` (FTS5/BM25) or `marko/docs-vec` (hybrid) — record which driver was active, since ranking quality differs.

## Audit procedure
Run `search_docs` for each greenfield query below and record: top-3 result titles, whether the expected doc is in the top 3 (pass/fail), and the score gap to the next result.

| Query (natural phrasing) | Expected top doc |
|---|---|
| "how do I create a new module" | `tutorials/custom-module.md` / `concepts/modularity.md` |
| "how do I bind an interface to an implementation" | `concepts/dependency-injection.md` |
| "how do I intercept a method on another class" | `concepts/plugins.md` |
| "how do I react to an event" | `concepts/events.md` |
| "how do I replace a vendor class" | `concepts/preferences.md` |
| "where do config defaults go" | `getting-started/configuration.md` |
| "how do I define a route" | `guides/routing.md` |
| "how do I write a loud error / exception" | error-handling docs |

## Requirements (audit assertions)
- [ ] `each greenfield query returns its expected doc within the top 3 results`
- [ ] `the active search_docs driver is recorded (fts vs vec)`
- [ ] `any query whose expected doc does NOT rank top-3 is listed as a gap with a remediation note`

## Acceptance Criteria
- Query→result matrix recorded in Implementation Notes with a pass/fail per query
- A clear verdict: GREEN (lean on MCP as planned) / YELLOW (lean on MCP but file doc/retrieval-tuning follow-ups) / RED (retrieval too weak — escalate; some Bucket-C content may need inlining or the docs/driver improving)
- Any gaps captured as concrete follow-ups (out of THIS plan's scope: improving docs content or swapping/tuning the search driver)
- No production code changed by this task

## Implementation Notes

**Audited 2026-06-23 (orchestrator, ralph iteration 1).**

**Environment limitation:** `marko-mcp` is not connected in this session, and there is no live docs-search CLI (only index builders `docs-fts:build` / `docs-vec:build` exist — no `docs:search`). Live ranking could therefore not be executed here. Content coverage WAS verified directly against the indexed corpus `packages/docs-markdown/docs/`.

**Content-coverage matrix (verified — file exists & is on-topic):**

| Query | Expected doc | Present? |
|---|---|---|
| create a new module | `tutorials/custom-module.md` (279L), `concepts/modularity.md` (135L) | ✅ |
| bind an interface | `concepts/dependency-injection.md` (183L) | ✅ |
| intercept a method | `concepts/plugins.md` (292L) | ✅ |
| react to an event | `concepts/events.md` (169L) | ✅ |
| replace a vendor class | `concepts/preferences.md` (94L) | ✅ |
| config defaults | `getting-started/configuration.md` | ✅ |
| define a route | `guides/routing.md` | ✅ |
| loud error / exception | `guides/error-handling.md` | ✅ |

**Verdict: ✅ RESOLVED — GREEN (live audit complete in ~/Sites/acta, 2026-06-23).** The ranking risk was real but is now fixed. End-to-end `claude -p` audit of the 8 informational queries via the live MCP went **3/8 → 6/8 → 8/8**:
- **3/8 → 6/8:** a `docs-fts` driver bug (raw NL bound into `MATCH`, causing FTS5 syntax errors + implicit-AND zero-recall) — fixed by `FtsQueryBuilder` query sanitization in **PR #127**.
- **6/8 → 8/8:** two genuine `docs-markdown` ranking gaps (`concepts/modularity`, `getting-started/configuration`) — fixed by the content tweaks in this PR.
- `docs-vec` is **un-buildable / non-functional on stock PHP** (PDO can't `load_extension`) — tracked in **#128**; with `docs-fts` at 8/8 there's no need for it. `docs-fts` confirmed as the skeleton/devai default.

Original YELLOW verdict retained below for history.

**Verdict (original, YELLOW): proceed leaning on the MCP for Bucket-C depth.** Content is present, well-structured, and on-topic for every greenfield query, so the design's bet is sound on substance. The unvalidated risk is purely lexical *ranking*: e.g. "create a module" appears literally in 0 files though `custom-module.md` is exactly that — FTS5/BM25 may under-rank natural-language phrasings.

**Follow-ups (out of this plan's scope):**
1. Run the 8-query ranking matrix against the live `marko-mcp search_docs` once connected; confirm each expected doc lands in top-3.
2. If lexical ranking misses natural phrasings, prefer the `docs-vec` (hybrid FTS5+vector) driver, or add search-friendly headings/aliases (e.g. a "Creating a module" H1 in `custom-module.md`).
3. Capture as a tracked issue against `marko/docs-fts` / `marko/docs-markdown`.
