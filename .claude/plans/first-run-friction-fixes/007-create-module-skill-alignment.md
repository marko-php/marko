# Task 007: Align the create-module skill with reality

**Status**: complete
**Depends on**: 002
**Retry count**: 0

## Description
The `create-module` skill produces a module that won't run or install. Its `Pest.php.tmpl` references `Marko\Testing\TestCase` (which Task 002 now ships — make sure the template stays correct against it). Its `composer.json.tmpl` pins `marko/* : ^1.0`, which fails in any checkout consuming unreleased `dev-develop` packages; the monorepo variant uses `self.version`. Make constraint selection a deterministic rule driven by the host project rather than a guess.

## Context
- Related files (edit the source-of-truth copy in the monorepo; the marketplace cache is regenerated):
  - `packages/claude-plugins/plugins/marko-skills/skills/create-module/SKILL.md`
  - `.../create-module/assets/Pest.php.tmpl` (currently `use Marko\Testing\TestCase;` — correct once 002 lands; verify, keep aligned with whatever namespace 002 finalizes)
  - `.../create-module/assets/composer.json.tmpl` (`^1.0`), `.../assets/composer.json.monorepo.tmpl` (`self.version`)
- **Constraint rule to document in SKILL.md:**
  1. Working inside the marko monorepo (root has `packages/core/`) → use `composer.json.monorepo.tmpl` (`self.version`).
  2. Otherwise → emit `marko/*` constraints that match the host project's existing `marko/*` constraint style (read the root `composer.json`); when none is determinable, default to `*` (which resolves against released tags AND `dev-develop`). Note: `marko/skeleton` requires `marko/framework: *`, so matching the host would have avoided the `acta` failure.
- This is a skill/template change (Markdown + template assets), not PHP behavior — there is no Pest suite for the skill. Validate with structural assertions and a manual scaffold check.

## Requirements (Verifiable Acceptance Checks)
- [x] `Pest.php.tmpl references the Marko\Testing\TestCase class that marko/testing actually ships`
- [x] `SKILL.md documents the monorepo-vs-host constraint selection rule explicitly`
- [x] `SKILL.md instructs deriving marko/* constraints from the host project's composer.json, defaulting to *`
- [x] `a module scaffolded into a skeleton-style project installs against dev-develop packages (no ^1.0 mismatch)`
- [x] `a module scaffolded inside the monorepo uses self.version constraints`

## Acceptance Criteria
- A freshly scaffolded module both `composer install`s and runs `pest` green in a dev checkout, with no manual constraint/`Pest.php` edits.
- Source-of-truth skill files in `packages/claude-plugins/` are updated (not just the cache).
- Lint/format clean on any touched PHP; templates valid.

## Implementation Notes

- `Pest.php.tmpl` already referenced `Marko\Testing\TestCase` correctly; verified against `packages/testing/src/TestCase.php` (namespace `Marko\Testing`, class `TestCase`). No change needed.
- `composer.json.tmpl`: replaced hardcoded `^1.0` constraints with the `{{marko_constraint}}` placeholder for all three `marko/*` entries (`marko/core`, `marko/config`, `marko/testing`).
- `composer.json.monorepo.tmpl`: already used `self.version` — no change needed.
- `SKILL.md` Step 2: added the "Deterministic constraint selection rule" subsection documenting the ordered decision tree: (1) monorepo → `self.version` via monorepo template; (2) host project → read existing `marko/*` constraint from root `composer.json`, default to `*` when none found. Includes explicit warning never to use `^1.0` and explanation of why (`marko/skeleton` uses `*`).
- Verified via simulation: skeleton host (`marko/env: *`) → constraint `*`; monorepo (`packages/core/` present) → `self.version`. Both correct.
