# Task 006: CursorAgent — route AGENTS.md + `.cursor/rules/marko.mdc` through GuidelinesWriter

**Status**: completed
**Depends on**: 001, 003
**Retry count**: 0

## Description
Route both files Cursor writes through `GuidelinesWriter`: the root AGENTS.md and the `.cursor/rules/marko.mdc` rule file (currently clobbered every run). The `.mdc` carries YAML frontmatter (`---\ndescription…\nalwaysApply: true\n---`).

**Frontmatter gotcha (must follow exactly).** Cursor (and every YAML-frontmatter parser) requires the opening `---` to be the VERY FIRST line of the file. An `<!-- BEGIN marko:devai -->` HTML comment placed before `---` breaks frontmatter detection and silently disables the rule. Therefore the markers must NOT wrap the frontmatter. On create, the file layout is:

```
---
description: Marko Framework guidelines
alwaysApply: true
---

<!-- BEGIN marko:devai -->
…generated body…
<!-- END marko:devai -->
```

The frontmatter block sits OUTSIDE (above) the BEGIN marker; only the body is wrapped. Practically: the agent assembles the frontmatter + a blank line, then calls `GuidelinesWriter::write()` for the body region — i.e. the frontmatter is part of the "content outside markers" that the writer preserves on update. On create, the agent writes the frontmatter prefix itself and lets the writer wrap the body beneath it. Confirm the resulting file's first line is `---`.

## Context
- File: `packages/devai/src/Agents/CursorAgent.php` — `writeGuidelines()` (writes `.cursor/rules/marko.mdc` unconditionally and AGENTS.md only-if-absent).
- `registerMcpServer()` (writes `.cursor/mcp.json`) is OUT OF SCOPE — leave untouched.
- **Breaking existing assertions to update:** `CursorAgentTest.php` line 64 (`AGENTS.md ->toBe('# Marko Guidelines')`) and line 68 (`->toBe('# Custom content')`, which currently asserts the only-if-absent back-off). Line 64 must become `->toContain(...)` + a marker-presence assertion. Line 68 now describes the marker-strip back-off case: a pre-existing AGENTS.md WITHOUT markers must be left untouched (`->toBe('# Custom content')` still holds, but the rationale changes from "only-if-absent" to "no markers → back off"). Keep the byte-equality there since back-off preserves bytes exactly.
- Tests: `packages/devai/tests/Unit/Agents/CursorAgentTest.php`.

## Requirements (Test Descriptions)
- [x] `it creates the cursor mdc rule file with the yaml frontmatter as the first line and the body inside the marker region`
- [x] `it preserves the frontmatter and any user content outside the markers in an existing mdc file`
- [x] `it creates AGENTS.md via the writer when it does not exist`
- [x] `it leaves a marker-stripped mdc file untouched`
- [x] `it leaves a pre-existing marker-less AGENTS.md untouched`
- [x] `it does not modify cursor MCP registration behavior`

## Acceptance Criteria
- All requirements have passing tests
- Both `.mdc` and AGENTS.md route through the writer
- The generated `.mdc` file's first line is `---` (frontmatter not wrapped by a leading HTML comment)
- MCP code path unchanged
- Code follows code standards

## Implementation Notes
- `CursorAgent::writeGuidelines()` now routes both `.cursor/rules/marko.mdc` and `AGENTS.md` through `GuidelinesWriter::write()`.
- For the `.mdc` file on CREATE: writes the YAML frontmatter prefix directly (`---\n...\n---\n\n`), then appends the markers+body inline (so frontmatter is "outside markers" and preserved on updates).
- For the `.mdc` file on UPDATE: delegates entirely to `GuidelinesWriter::write()`, which finds existing markers and replaces only the body between them, preserving the frontmatter prefix byte-for-byte.
- For `AGENTS.md`: delegates entirely to `GuidelinesWriter::write()` — creates with markers on first run, skips (back-off) if file exists without markers.
- `registerMcpServer()` (`.cursor/mcp.json`) is untouched.
