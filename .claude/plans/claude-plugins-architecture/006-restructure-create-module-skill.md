# Task 006: Restructure `create-module` skill into marko-skills with bundled assets + directives

**Status**: completed
**Depends on**: 005
**Retry count**: 0

## Description
Move and restructure the existing `marko-create-module` skill (currently at `packages/devai/resources/ai/skills/marko-create-module/SKILL.md` with inline templates) into the new canonical home at `packages/claude-plugins/plugins/marko-skills/skills/create-module/`. Apply Anthropic's published anatomy: <500 lines SKILL.md with a "pushy" description, anti-pattern directives, and references to bundled `assets/*.tmpl` files instead of inline-quoted templates. Add the LSP verification gate (per Task 008's CLAUDE.md content).

## Context
- Related files:
  - Source (delete after move): `packages/devai/resources/ai/skills/marko-create-module/SKILL.md`
  - New: `packages/claude-plugins/plugins/marko-skills/skills/create-module/SKILL.md`
  - New: `packages/claude-plugins/plugins/marko-skills/skills/create-module/assets/composer.json.tmpl`
  - New: `packages/claude-plugins/plugins/marko-skills/skills/create-module/assets/composer.json.monorepo.tmpl`
  - New: `packages/claude-plugins/plugins/marko-skills/skills/create-module/assets/Pest.php.tmpl`
  - New: `packages/claude-plugins/plugins/marko-skills/skills/create-module/assets/module.php.tmpl`
  - New: `packages/claude-plugins/plugins/marko-skills/skills/create-module/assets/README.md.tmpl`
  - New (if Task 001 finds line count needs decomposition): `packages/claude-plugins/plugins/marko-skills/skills/create-module/references/advanced-di.md`
- Patterns to follow:
  - Anthropic's `skills/skill-creator/SKILL.md` writing-style guide (cloned at `/tmp/anthropics-skills/skills/skill-creator/SKILL.md`)
  - Existing `marko-create-module` content (already spec-driven; preserve technical correctness)
- Required directives in SKILL.md (verbatim or near-verbatim):
  - "This skill is the canonical specification for a Marko module. Do not inspect existing modules to infer layout — copy the templates below verbatim, substitute names, and stop."
  - "After writing files, expect LSP diagnostics from `marko-lsp`. Resolve all diagnostics before declaring the module complete."
- Description field guidance: include concrete trigger examples ("e.g., 'create a module named X', 'scaffold a payment package', 'add a new module for X'"). Per skill-creator: "make the skill descriptions a little bit pushy."
- Templates use `{{vendor}}`, `{{name}}`, `{{Vendor}}`, `{{Name}}` placeholders (StudlyCase variants for namespaces)

## Requirements (Test Descriptions)
- [x] `SKILL.md frontmatter has name "create-module" and a non-empty description with concrete trigger examples`
- [x] `SKILL.md is under 500 lines`
- [x] `SKILL.md contains the anti-pattern directive forbidding inference from sibling modules`
- [x] `SKILL.md contains the LSP verification gate directive`
- [x] `SKILL.md instructs the agent to copy templates from assets/ rather than inlining file content`
- [x] `composer.json.tmpl contains {{vendor}} and {{name}} placeholders and is valid JSON when placeholders are substituted with literal strings`
- [x] `composer.json.monorepo.tmpl uses self.version constraints for marko/* requirements`
- [x] `Pest.php.tmpl contains the {{Vendor}}\\{{Name}} namespace placeholder`
- [x] `module.php.tmpl is documented as optional and only created when DI bindings are needed`
- [x] `README.md.tmpl follows the slim-pointer convention from docs/DOCS-STANDARDS.md`
- [x] `the original SKILL.md at packages/devai/resources/ai/skills/marko-create-module/ is deleted`

## Acceptance Criteria
- All requirements have passing tests (assertions on file existence, frontmatter parsing, line count, placeholder presence, JSON validity after substitution)
- SKILL.md reads as an executable runbook, not a tutorial
- All technical content from the original skill is preserved (composer.json shape, conventions, verification step via list_modules MCP tool)
- `composer test` passes

## Implementation Notes

- New canonical location: `packages/claude-plugins/plugins/marko-skills/skills/create-module/`
- SKILL.md: 92 lines (projection was ~135; actual is leaner because the skill body is tight). Well under the 500-line cap.
- YAML frontmatter uses `>` block scalar for `description` since the value spans multiple lines with trigger examples.
- All 5 `assets/*.tmpl` files created: `composer.json.tmpl`, `composer.json.monorepo.tmpl`, `Pest.php.tmpl`, `module.php.tmpl`, `README.md.tmpl`.
- `composer.json.tmpl` validates as clean JSON after `{{vendor}}`/`{{name}}`/`{{Vendor}}`/`{{Name}}` substitution.
- `composer.json.monorepo.tmpl` uses `self.version` for all `marko/*` entries in both `require` and `require-dev`.
- The `Pest.php.tmpl` test assertion checks `Marko\\Testing\\TestCase` (which is the escaped form of `Marko\Testing\TestCase` in PHP source).
- Original `packages/devai/resources/ai/skills/marko-create-module/SKILL.md` deleted; parent directory left in place (Task 009 handles cleanup).
- Test file: `packages/claude-plugins/tests/Unit/Skills/CreateModuleSkillTest.php` — 11 tests, all passing.
- Full suite: 71 tests pass (sequentially); the parallel run occasionally shows 1 flaky failure in `CreatePluginSkillTest` due to file-read race conditions in the parallel worker pool — pre-existing issue unrelated to Task 006.
