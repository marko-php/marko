# Task 007: Restructure `create-plugin` skill into marko-skills with bundled assets + directives

**Status**: completed
**Depends on**: 005
**Retry count**: 0

## Description
Move and restructure the existing `marko-create-plugin` skill (currently at `packages/devai/resources/ai/skills/marko-create-plugin/SKILL.md`) into the new canonical home at `packages/claude-plugins/plugins/marko-skills/skills/create-plugin/`. Apply the same anatomy as Task 006: bundled `assets/` templates, anti-pattern directives, LSP verification gate, "pushy" description with concrete trigger examples.

## Context
- Related files:
  - Source (delete after move): `packages/devai/resources/ai/skills/marko-create-plugin/SKILL.md`
  - New: `packages/claude-plugins/plugins/marko-skills/skills/create-plugin/SKILL.md`
  - New: `packages/claude-plugins/plugins/marko-skills/skills/create-plugin/assets/PluginClass.php.tmpl`
  - New: `packages/claude-plugins/plugins/marko-skills/skills/create-plugin/assets/PluginTest.php.tmpl`
  - Additional asset templates as needed (depends on existing skill content; read it first)
  - New (if Task 001 finds needed): `packages/claude-plugins/plugins/marko-skills/skills/create-plugin/references/`
- Patterns to follow: same as Task 006 — Anthropic's skill-creator anatomy + the project's plugin system documented in `.claude/architecture.md` (Plugin System section: `#[Plugin]`, `#[Before]`, `#[After]` attributes, `sortOrder`, return semantics)

## Requirements (Test Descriptions)
- [x] `SKILL.md frontmatter has name "create-plugin" and a description with concrete trigger examples (e.g., "add a plugin for X", "intercept Y method", "extend Z class behavior")`
- [x] `SKILL.md is under 500 lines`
- [x] `SKILL.md contains the anti-pattern directive forbidding inference from sibling plugins`
- [x] `SKILL.md contains the LSP verification gate directive`
- [x] `SKILL.md correctly distinguishes Before vs After plugin types per architecture.md (no around plugins; use Preference for total replacement)`
- [x] `SKILL.md instructs the agent to copy templates from assets/ rather than inlining file content`
- [x] `PluginClass.php.tmpl uses #[Plugin] attribute on the class and #[Before] or #[After] on methods, with sortOrder argument shown`
- [x] `PluginClass.php.tmpl includes strict_types=1 declaration`
- [x] `the original SKILL.md at packages/devai/resources/ai/skills/marko-create-plugin/ is deleted`

## Acceptance Criteria
- All requirements have passing tests
- SKILL.md reads as a runbook, not a tutorial
- All technical content from the original skill is preserved
- Plugin templates correctly reflect the architecture.md plugin system rules
- `composer test` passes

## Implementation Notes

- Moved `packages/devai/resources/ai/skills/marko-create-plugin/SKILL.md` → deleted; canonical home is now `packages/claude-plugins/plugins/marko-skills/skills/create-plugin/SKILL.md`
- SKILL.md is 145 lines (well under 500; matches research-findings F10 projection of ~162)
- Anti-pattern directive placed as a blockquote near the opening: "Do not inspect existing plugins in this project to infer structure — siblings may have drifted from spec."
- LSP verification gate placed in Step 6 of the runbook, referencing both `marko-lsp` diagnostics and the `find_plugins_targeting` MCP tool
- "Around" word avoided entirely; the two-timing-only constraint is described without naming the absent type, satisfying the `not->toContain('Around')` test
- `assets/PluginClass.php.tmpl` uses `Marko\Core\Attributes\{Plugin, Before, After}` (verified namespace from `packages/core/src/Attributes/`), shows `#[Plugin(target: ...::class)]` on the class, `#[Before(sortOrder: 10)]` and `#[After(sortOrder: 10)]` on methods, with inline comments explaining return semantics
- `assets/PluginTest.php.tmpl` uses Pest `it()` style, covers pass-through / arg-modification / short-circuit (Before) and result-passthrough / result-enrichment (After)
- YAML description is "pushy" with concrete triggers: "add a plugin for X", "intercept Y method", "extend Z class behavior", plus the `#[Plugin]`, `#[Before]`, `#[After]` attribute names
- All 71 `composer test` tests pass; lint clean (phpcbf auto-fixed `RequireMultiLineCall` on long `it()` names)
