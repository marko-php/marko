# Task 014: Create marko/docs-markdown package and relocate docs/ content

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create `marko/docs-markdown` at `packages/docs-markdown/`. Relocate the monorepo's existing `docs/` directory contents into `packages/docs-markdown/docs/` so the markdown becomes a composer-distributable asset. This package owns the canonical Marko documentation content.

IMPORTANT: The monorepo's `docs/` currently hosts the Astro/Starlight site content under `docs/src/content/docs/**`. Moving it without updating the site build (task 015) will break `marko.build`. Task 014 MUST be landed together with task 015 in a single commit/PR (they are atomic: content move + pipeline update). If that is not feasible, keep `docs/` in place and have task 014 copy (not move) the content, then task 015 cuts over and removes the original.

## Context
- Source: `/Users/markshust/Sites/marko/docs/`
- Destination: `packages/docs-markdown/docs/`
- Namespace: `Marko\DocsMarkdown\` (minimal — this is primarily a data package)
- Exposes a single `MarkdownRepository` class that lists pages and returns raw markdown by id
- All existing markdown + images + navigation files move wholesale

## Requirements (Test Descriptions)
- [x] `it has composer.json with name marko/docs-markdown and PSR-4 namespace Marko\\DocsMarkdown\\`
- [x] `it ships docs content under docs/ inside the package`
- [x] `it has MarkdownRepository with listAllPages and getRawMarkdown id methods`
- [x] `it returns file content matching original monorepo docs files`
- [x] `it preserves the original navigation metadata file or equivalent index`
- [x] `it exposes absolute path to docs root via a dedicated accessor`

## Acceptance Criteria
- No content lost in move; git log shows content moved, not deleted
- `MarkdownRepository::listAllPages()` returns every .md file under docs/
- `getRawMarkdown` returns exact bytes of requested page

## Implementation Notes
(Filled in by programmer during implementation)
