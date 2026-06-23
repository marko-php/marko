# Task 011: Refresh devai docs/README for override behavior

**Status**: completed
**Depends on**: 002, 003, 004, 005, 006, 007, 008, 009, 010
**Retry count**: 0

## Description
Update the devai package documentation to describe the new override model: marker-delimited generated regions, that content outside markers is the user's and is never touched, that removing the markers makes devai back off entirely, and that depth comes from the `marko-mcp` `search_docs` tool (no static reference docs shipped). Reflect that all six agents now behave uniformly.

## Context
- Files: `packages/devai/README.md` (slim pointer per `docs/DOCS-STANDARDS.md`) and the devai docs page under the docs content package if one exists.
- The post-implementation `doc-updater` agent also handles docs sync — keep README a slim pointer (title, install, quick example, docs link); put the override-model detail on the docs page, not the README.
- Document the marker format and the create / merge / back-off semantics so users understand how to take full ownership of a file.

## Requirements (Test Descriptions)
- [x] `it documents the marker-delimited override model on the devai docs page`
- [x] `it states that content outside markers is never modified`
- [x] `it states that removing markers makes devai stop managing the file`
- [x] `it keeps the README a slim pointer per docs standards`

## Acceptance Criteria
- All requirements have passing tests (doc-standards/readme tests under `packages/devai/tests`)
- README remains a slim pointer; detail lives on the docs page
- No mention of shipped static reference docs (none are shipped)
- Code follows code standards

## Implementation Notes
- Added `## Override model` section to `packages/docs-markdown/docs/packages/devai.md` documenting:
  - The `<!-- BEGIN marko:devai -->` / `<!-- END marko:devai -->` marker format
  - That content outside markers is never modified
  - That removing markers makes devai back off entirely (with loud notice)
  - That depth comes from `search_docs` MCP tool, not static reference docs
- `packages/devai/README.md` unchanged — already a slim pointer per DOCS-STANDARDS
- Added `packages/devai/tests/Unit/DocsPageTest.php` with 4 tests covering all requirements
