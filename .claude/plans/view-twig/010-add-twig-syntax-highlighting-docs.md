# Task 010: Add Twig syntax highlighting to docs site

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
Register `twig` as a Shiki language in the docs site's Astro Starlight config so Twig code blocks in `.md`/`.mdx` files render with syntax highlighting. Twig is bundled with Shiki natively, so this is a one-line addition to the `langs` array — no custom grammar JSON required (unlike the existing Latte support).

## Context
- Related files:
  - `docs/astro.config.mjs` (modify — add `'twig'` to `shiki.langs`)
- Current state of relevant lines:
  ```js
  shiki: {
      langs: [latteGrammar, 'dotenv'],
      langAlias: { ... },
  },
  ```
- After change:
  ```js
  shiki: {
      langs: [latteGrammar, 'twig', 'dotenv'],
      langAlias: { ... },
  },
  ```
- No new grammar file needed — Shiki ships Twig support (it's one of Shiki's bundled languages, identified by the string `'twig'`)
- Verification step: after the edit, build the docs site (`cd docs && npm run build`) and confirm there are no warnings about the `twig` language being unknown. If Shiki rejects the string, fall back to importing the grammar from `shiki/langs/twig.mjs` and pushing the imported value into `langs` (same pattern as `latteGrammar`).
- This task does not write Twig documentation content (that's doc-updater's job in the post-implementation pipeline)

## Requirements (Test Descriptions)
- [ ] `it registers twig as a Shiki language in astro.config.mjs`
- [ ] `it keeps the existing latte grammar registration`
- [ ] `it keeps the existing dotenv language registration`

## Acceptance Criteria
- `docs/astro.config.mjs` includes `'twig'` in the `shiki.langs` array
- No regressions to existing Latte highlighting
- Docs site builds successfully (`cd docs && npm run build`)
- Twig code fences in any `.md` file render with syntax highlighting

## Implementation Notes
(Left blank — filled in by programmer during implementation)
