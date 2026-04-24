# Task 015: Update marko.build docs pipeline for new location

**Status**: pending
**Depends on**: 014
**Retry count**: 0

## Description
Update the marko.build documentation build pipeline to read markdown from `packages/docs-markdown/docs/` instead of the old monorepo-root `docs/`. The website at https://marko.build/docs must continue to render exactly as before.

## Context
- **marko.build lives in a separate repository**: `~/Sites/marko.build`. Astro/Starlight site deployed to Cloudflare Pages. A GitHub Actions workflow in that repo pulls content from the Marko monorepo — update that workflow to point at `packages/docs-markdown/docs/` instead of the old `docs/` path.
- Concrete steps:
  1. Inspect `~/Sites/marko.build/.github/workflows/` to find the workflow that syncs or fetches docs content
  2. Update whatever path/URL/reference points at `devtomic/marko` monorepo's `docs/` to point at `packages/docs-markdown/docs/` instead
  3. Update any Astro/Starlight config inside `~/Sites/marko.build/` (e.g., `astro.config.mjs`, Starlight `sidebar` entries) that references the old path
  4. Trigger a Cloudflare Pages preview build and verify rendering before merging
- This task therefore spans TWO repositories. Land task 014 (content move) and task 015 (site repo updates) atomically — either via coordinated PRs landing the same day, or by keeping a copy in `docs/` during the transition window (with a `DEPRECATED.md` note) and removing it after the site repo cuts over.
- Must produce a clean Cloudflare Pages build with identical rendered output.

## Requirements (Test Descriptions)
- [ ] `it points the docs build pipeline at packages/docs-markdown/docs/ as source`
- [ ] `it produces a clean build of marko.build/docs after the path change`
- [ ] `it renders the same pages count as before the migration`
- [ ] `it preserves image and asset paths through the rename`
- [ ] `it updates any navigation config to reflect the new source path`

## Acceptance Criteria
- Docs site builds successfully
- No broken links in a smoke crawl of build output
- Navigation structure unchanged from user's perspective

## Implementation Notes
(Filled in by programmer during implementation)
